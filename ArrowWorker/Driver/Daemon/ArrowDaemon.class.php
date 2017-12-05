<?php
/**
 * User: Arrow
 * Date: 2016/8/1
 * Time: 19:52
 * Modified by louis at 2017/02/03 23:58
 */

namespace ArrowWorker\Driver\Daemon;
use ArrowWorker\Driver\Daemon AS daemon;
use ArrowWorker\Driver\Daemon\ArrowThread;
use ArrowWorker\Driver\Daemon\GeneratorTask;

/**
 * Class ArrowDaemon
 * @package ArrowWorker\Driver\Daemon
 */
class ArrowDaemon extends daemon
{
    /**
     * pid文件路径
     * @var string
     */
    private static $pid_Path    = '/var/run';

    /**
     * pid文件完整路径
     * @var string
     */
    private static $pid_File    = '';

    /**
     * 默认pid文件名
     * @var string
     */
    private static $pid_Name    = 'ArrowWorker';

    /**
     * 应用名称
     * @var string
     */
    private static $App_Name    = 'ArrowWorker';

    /**
     * 运行用户
     * @var string
     */
    private static $user        = 'root';

    /**
     * 是否退出 标识
     * @var bool
     */
    private static $terminate   = false;

    /**
     * 日志前缀时间时区
     * @var string
     */
    private static $tipTimeZone = 'UTC';

    /**
     * 任务数量
     * @var int
     */
    private static $jobNum      = 0;

    /**
     * 进程执行权限
     * @var int
     */
    private static $umask       = 0;

    /**
     * 进程日志文件
     * @var string
     */
    private static $output      = '/var/log/ArrowWorker.log';

    /**
     * 是否以单例模式运行
     * @var bool
     */
    private static $isSingle    = true;

    /**
     * 是否是用多线程模式运行
     * @var bool
     */
    private static $isMultiThr  = false;

    /**
     * 任务map
     * @var bool
     */
    private static $jobs        = [];

    /**
     * 任务进程 ID map
     * @var Array
     */
    private static $tmpPid      = [];

    /**
     * 线程池
     * @var Array
     */
    private static $threadMap   = [];

    /**
     * 协程池（即将废弃）
     * @var Array
     */
    private static $scheduleMap = [];

    /**
     * 单个进程开启的线程数
     * @var Array
     */
    private static $threadNum   = 6;

    /**
     * 进程内任务执行状态 开始时间、运行次数、结束时间
     * @var Array
     */
    private static $workerStat  = ['start' => null, 'count' => 0, 'end' => null];

    /**
     * 是否使用协程,默认不使用
     * @var bool
     */
    private static $enableGenerator = false;


    /**
     * ArrowDaemon constructor.
     * @param array $config
     */
    public function __construct($config)
    {
        parent::__construct($config);
        //设置运行日志级别
        error_reporting(self::$config['level']);

        self::$isSingle = true;
        self::$user     = isset(self::$config['user']) ? self::$config['user'] : self::$user;
        self::$pid_Name = isset(self::$config['pid'])  ? self::$config['pid']  : self::$pid_Name;
        self::$output   = isset(self::$config['log'])  ? self::$config['log']  : self::$output;
        self::$threadNum = isset(self::$config['thread'])  ? self::$config['thread']  : self::$threadNum;
        self::$App_Name = isset(self::$config['name']) ? self::$config['name'] : self::$App_Name;
        self::$enableGenerator = isset(self::$config['enableGenerator']) ? self::$config['enableGenerator'] : self::$enableGenerator;
        $this -> _environmentCheck();
        $this -> _daemonMake();
    }

    /**
     * init 单例模式初始化类
     * @author Louis
     * @param $config
     * @return ArrowDaemon
     */
    static function Init($config) : self
    {
        if(!self::$daemonObj)
        {
            self::$daemonObj = new self($config);
        }
        return self::$daemonObj;
    }

    /**
     * _environmentCheck 运行环境/扩展检测
     * @author Louis
     */
    private function _environmentCheck()
    {
        if (php_sapi_name() != "cli")
        {
            die("ArrowWorker hint : only run in command line mode\n");
        }

        if ( ! function_exists('pcntl_signal_dispatch'))
        {
            declare(ticks = 10);
        }

        if ( ! function_exists('pcntl_signal'))
        {
            $message = 'php environment do not support pcntl_signal';
            $this -> _writeLog($message);
            throw new Exception($message);
        }

        $fl = fopen(self::$output, 'w') or die("ArrowWorker hint : cannot create log file");
              fclose($fl);

        if (function_exists('gc_enable'))
        {
            gc_enable();
        }
        
        self::$isMultiThr = extension_loaded('pthreads');

    }

    /**
     * _daemonMake  进程脱离终端
     * @author Louis
     */
    private function _daemonMake()
    {
        
        set_time_limit(0);

        if (self::$isSingle == true)
        {
            self::$pid_File = self::$pid_Path . "/" . self::$pid_Name . ".pid";
            $this -> _checkPidfile();
        }

        umask(self::$umask);

        if (pcntl_fork() != 0)
        {
            exit();
        }

        posix_setsid();

        if (pcntl_fork() != 0)
        {
            exit();
        }

        chdir("/");
        $proStartTime = date("Y-m-d H:i:s");
        $this -> _userSet(self::$user) or die("ArrowWorker hint : Setting process user failed！");
        $this -> _resetStd();
        $this -> _setProcessName("ArrowWorker V1.5 --By Louis --started at ".$proStartTime);
        if (self::$isSingle==true)
        {
            $this -> _createPidfile();
        }

    }

    /**
     * _resetStd 重置标准输入输出
     * @author Louis
     */
    private function _resetStd()
    {
        global $STDOUT, $STDERR;
        $handle = fopen(self::$output, "a");
        if ($handle)
        {
            unset($handle);
            fclose(STDOUT);
            fclose(STDERR);
            $STDOUT = fopen(self::$output, 'a');
            $STDERR = fopen(self::$output, 'a');
        }
        else
        {
            $this -> _writeLog("ArrowWorker hint : can not open stdoutFile");
        }
    }

    /**
     * _createPidfile 创建进程pid文件
     * @author Louis
     */
    private function _createPidfile()
    {

        if (!is_dir(self::$pid_Path))
        {
            mkdir(self::$pid_Path);
        }

        $fp = fopen(self::$pid_File, 'w') or die("cannot create pid file");
        fwrite($fp, posix_getpid());
        fclose($fp);

        $this -> _writeLog("create pid file " . self::$pid_File);
    }

    /**
     * _checkPidfile 检测进程pid文件
     * @author Louis
     */
    private function _checkPidfile()
    {

        if (!file_exists(self::$pid_File))
        {
            return true;
        }

        $pid = intval(file_get_contents(self::$pid_File));

        if ($pid > 0 && posix_kill($pid, 0))
        {
            $this -> _writeLog("ArrowWorker hint : Daemon process is already started");
        }
        else
        {
            $this -> _writeLog("ArrowWorker hint : process ended abnormally , Check your program." . self::$pid_File);
        }

        exit(1);

    }


    /**
     * _setSignalHandler 进程信号处理设置
     * @author Louis
     * @param string $type 设置信号类型（子进程/监控进程）
     * @param int $lifecycle 闹钟周期
     */
    private function _setSignalHandler(string $type = 'parentsQuit', int $lifecycle=0)
    {
        switch($type)
        {
            case 'workerHandler':
                pcntl_signal(SIGTERM, array(__CLASS__, "signalHandler"),false);
                pcntl_signal(SIGALRM, array(__CLASS__, "signalHandler"),false);
                pcntl_signal(SIGUSR1, array(__CLASS__, "signalHandler"),false);
                pcntl_alarm($lifecycle);
                break;
            default:
                pcntl_signal(SIGCHLD, array(__CLASS__, "signalHandler"),false);
                pcntl_signal(SIGTERM, array(__CLASS__, "signalHandler"),false);
                pcntl_signal(SIGINT, array(__CLASS__, "signalHandler"),false);
                pcntl_signal(SIGQUIT, array(__CLASS__, "signalHandler"),false);
        }
    }


    /**
     * signalHandler 进程信号处理
     * @author Louis
     * @param int $signal
     * @return bool
     */
    public function signalHandler(int $signal)
    {
        switch($signal)
        {
            case SIGUSR1:
            case SIGALRM:
                self::$terminate = true;
                break;
            case SIGTERM:
            case SIGHUP:
            case SIGINT:
            case SIGQUIT:
                self::$terminate = true;
                $this -> _exitWorkers();
                break;
            default:
                return false;
        }

    }


    /**
     * _userSet 运行用户设置
     * @author Louis
     * @param string $name
     * @return bool
     */
    private function _userSet(string $name) : bool
    {

        $result = false;
        if (empty($name))
        {
            return true;
        }

        $user = posix_getpwnam($name);

        if ($user)
        {
            $uid = $user['uid'];
            $gid = $user['gid'];
            $result = posix_setuid($uid);
            posix_setgid($gid);
        }
        return $result;

    }


    /**
     * _setProcessName  进程名称设置
     * @author Louis
     * @param string $proName
     */
    private function _setProcessName(string $proName)
    {
        $proName = self::$App_Name.' -- '.$proName;
        if(function_exists('cli_set_process_title'))
        {
            @cli_set_process_title($proName);
        }
        elseif(extension_loaded('proctitle')&&function_exists('setproctitle'))
        {
            @setproctitle($proName);
        }
    }


    /**
     * start 挂载信号处理、生成任务worker、开始worker监控
     * @author Louis
     */
    public function Start()
    {

        self::$jobNum = count(self::$jobs,0);

        if(self::$jobNum == 0)
        {
            $this -> _writeLog("ArrowWorker hint : please add one task at least.");
            $this -> _deletePidAndLogExit();
        }
        $this -> _setSignalHandler('monitorHandler');
        $this -> _forkWorkders();
        $this -> _startMonitor();
    }

    /**
     * _exitWorkers 循环退出所有worker
     * @author Louis
     */
    private function _exitWorkers()
    {
        foreach(self::$tmpPid as $key => $val)
        {
            $result = posix_kill($key,SIGUSR1);
            if(!$result)
            {
                 posix_kill($key,SIGUSR1);
            }
        }
    }

    /**
     * _exitWorkers 开启worker监控
     * @author Louis
     */
    private function _startMonitor()
    {
        while (1)
        {
            if(self::$terminate)
            {
                $unExitedCount = 0;
                for($i = 0; $i<self::$jobNum; $i++)
                {
                    $unExitedCount += self::$jobs[$i]['pidCount'];
                }

                for ($i=0; $i<$unExitedCount; $i++)
                {
                    $status = 0;
                    $pid    = pcntl_wait($status, WUNTRACED);
                    $this -> _handleExited( $pid, $status );
                }
                $this -> _deletePidAndLogExit();
            }

            pcntl_signal_dispatch();

            $status = 0;
            //returns the process ID of the child which exited, -1 on error or zero if WNOHANG was provided as an option (on wait3-available systems) and no child was available
            $pid    = pcntl_wait($status, WUNTRACED);
            pcntl_signal_dispatch();
            $this -> _handleExited( $pid, $status, false );

        }
    }


    /**
     * _handleExited 处理退出的进程
     * @author Louis
     * @param int $pid
     * @param int $status
     * @param bool $isExit
     */
    private function _handleExited(int $pid, int $status, bool $isExit=true)
    {
        if ($pid > 0)
        {
            $taskGroupId = self::$tmpPid[$pid];
            self::$jobs[$taskGroupId]['pidCount']--;
            unset(self::$tmpPid[$pid]);
            if( !$isExit )
            {
                $this -> _forkOneWork($taskGroupId);
            }
            $this -> _writeLog("Task process(".self::$jobs[$taskGroupId]["processName"]."-".$pid.":".$status.") exited.");
        }
    }


    /**
     * _forkWorkders 给多有任务开启对应任务执行worker组
     * @author Louis
     */
    private function _forkWorkders()
    {
        for($i = 0; $i<self::$jobNum; $i++)
        {   
            while(self::$jobs[$i]['pidCount'] < self::$jobs[$i]['concurrency'])
            {
                $this -> _forkOneWork($i);
            }
            usleep(10000);
        }
    }


    /**
     * _forkOneWork 生成一个任务worker
     * @author Louis
     * @param int $taskGroupId
     */
    private function _forkOneWork(int $taskGroupId)
    {
        $pid = -1;

        $pid = pcntl_fork();
               
        if($pid > 0)
        {   
            self::$jobs[$taskGroupId]['pidCount']++;
            self::$tmpPid[$pid] = $taskGroupId;
        }
        elseif($pid==0)
        {   
            $this -> _runWorker($taskGroupId, self::$jobs[$taskGroupId]['lifecycle']);
        }
        else
        {   
            sleep(2);
        }
    }


    /**
     * _runWorker 常驻执行任务
     * @author Louis
     * @param int $index
     * @param int $lifecycle
     */
    private function _runWorker(int $index, int $lifecycle)
    {
        $this -> _setSignalHandler('workerHandler', $lifecycle );
        $this -> _setProcessName( self::$jobs[$index]['processName'] );
        if( self::$isMultiThr )
        {
            $this -> _threadRunTask( $index );
        }
        else
        {
            if( self::$enableGenerator )
            {
                $generatorCount = 0;
                while( $generatorCount<50 )
                {
                    $this -> newScheduleTask( $this-> _generatorRunTask( $index ) );
                    $generatorCount++;
                }

                $this -> _scheduleRun();
                
                self::$workerStat['end'] = time();
                $proWorkerTimeSum  = self::$workerStat['end'] - self::$workerStat['start'];
                $this -> _writeLog( self::$jobs[$index]['processName'].' finished '.self::$workerStat['count'].' times of its work in '.$proWorkerTimeSum.' seconds.' );
                exit(0);

            }
            else
            {
                $this -> _processRunTask( $index );
            }
        }
    }


    /**
     * _processRunTask 进程形式执行任务
     * @author Louis
     * @param int $index
     */
    private function _processRunTask(int $index)
    {
        self::$workerStat['start'] = time();
        $this -> _writeLog( self::$jobs[$index]['processName'].' started.');
        while( 1 )
        {
            if( self::$terminate )
            {
                self::$workerStat['end'] = time();
                $proWorkerTimeSum  = self::$workerStat['end'] - self::$workerStat['start'];
                $this -> _writeLog( self::$jobs[$index]['processName'].' finished '.self::$workerStat['count'].' times of its work in '.$proWorkerTimeSum.' seconds.' );
                exit(0);
            }
            pcntl_signal_dispatch();
            if( isset( self::$jobs[$index]['argv'] ) )
            {
                call_user_func_array( self::$jobs[$index]['function'], self::$jobs[$index]['argv'] );
            }
            else
            {
                call_user_func( self::$jobs[$index]['function'] );
            }
            pcntl_signal_dispatch();
            self::$workerStat['count']++;
        }
    }


    /**
     * _generatorRunTask 协程执行任务
     * @author Louis
     * @param int $index
     * @return \Generator
     */
    private function _generatorRunTask( int $index )
    {
        while( 1 )
        {

            if( isset( self::$jobs[$index]['argv'] ) )
            {
                call_user_func_array( self::$jobs[$index]['function'], self::$jobs[$index]['argv'] );
            }
            else
            {
                call_user_func( self::$jobs[$index]['function'] );
            }

            yield 1;    

        }
    }

    //协程执行任务

    /**
     * _scheduleRun
     * @author Louis
     */
    private function _scheduleRun()
    {
        while( 1 )
        {
            if ( self::$terminate )
            {
                break;
            }

            pcntl_signal_dispatch();

            foreach( self::$scheduleMap as $taskId => $task )
            {
                $return = $task -> run();
                self::$workerStat['count'] += $return;

                if ( $task->isFinished() )
                {
                    unset( self::$scheduleMap[$taskId] );
                }
            }
        }

    }

    /**
     * newScheduleTask 添加协程任务
     * @author Louis
     * @param \Generator $coroutine
     */
    private function newScheduleTask( \Generator $coroutine )
    {
        self::$scheduleMap[] = new GeneratorTask( $coroutine );
    }

    /**
     * _threadRunTask 线程执行任务
     * @author Louis
     * @param int $index
     */
    private function _threadRunTask(int $index)
    {
        //创建线程
        for( $i = 1; $i <= self::$threadNum; $i++  )
        {
            self::$threadMap[] = new ArrowThread( self::$jobs[$index]['processName'].'_thread_'.$i, self::$jobs[$index] );
        }

        //启动线程
        foreach( self::$threadMap as $workerThread )
        {
            $workerThread -> start();
        }

        self::$workerStat['start'] = time();

        //循环给线程分发任务
        while( 1 )
        {
            if( self::$terminate )
            {
                $threadsTaskCount = 0;
                //退出所有线程
                foreach( self::$threadMap as $key => $workerThread )
                {
                    $threadsTaskCount +=  $workerThread->taskCount;
                    $workerThread -> endThread();
                    $workerThread -> join();
                    unset( self::$threadMap[$key] );
                }   
                self::$workerStat['end'] = time();
                $proWorkerTimeSum  = self::$workerStat['end'] - self::$workerStat['start'];
                $this -> _writeLog(self::$jobs[$index]['processName'].' finished '.$threadsTaskCount.' times of its work in '.$proWorkerTimeSum.' seconds.');
                break;
            }

            pcntl_signal_dispatch();

           /*
            foreach( self::$threadMap as $workerThread )
            {
                //线程空闲
                if( !$workerThread -> hasTask )
                {
                    $workerThread -> pushTask( self::$jobs[$index] );
                    self::$workerStat['count']++;
                }
            }
           */
            usleep(20);
        }
    }

    /**
     * _deletePidAndLogExit 删除进程pid文件、记录退出信息后正常退出粗
     * @author Louis
     */
    private function _deletePidAndLogExit()
    {
        if (file_exists(self::$pid_File))
        {
            unlink(self::$pid_File);
            $this -> _writeLog("delete pid file " . self::$pid_File);
        }
        $this -> _writeLog("ArrowWork  hint ：monitor exits.");
        exit(0);

    }

    /**
     * addTask 添加任务及相关属性
     * @author Louis
     * @param array $job
     */
    public function AddTask( $job = [] )
    {
        
        if(!isset($job['function'])||empty($job['function']))
        {
            $this -> _writeLog("ArrowWork  hint ： one Task at least is needed.");
            exit(0);
        }

        $job['pidCount']    = 0;
        $job['lifecycle']   = (isset($job['lifecycle']) && is_int($job['lifecycle']))   ? $job['lifecycle']   : 0 ;
        $job['concurrency'] = (isset($job['concurrency']) && is_int($job['concurrency'])) ? $job['concurrency'] : 0 ;
        $job['processName'] = (!isset($job['proName'])||empty($job['proName'])) ? 'unnamed process' : $job['proName'];

        self::$jobs[] = $job;
    }

    /**
     * _writeLog 标准输出日志
     * @author Louis
     * @param string $message
     */
    private  function _writeLog(string $message)
    {
        date_default_timezone_set(self::$tipTimeZone);
        @printf("%s\tpid:%d\tppid:%d\t%s\n", date("Y-m-d H:i:s"), posix_getpid(), posix_getppid(), $message);
    }

}
