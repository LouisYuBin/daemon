<?php

namespace ArrowWorker\Component\Worker;

use ArrowWorker\App;
use ArrowWorker\Log;
use ArrowWorker\Component;
use ArrowWorker\Daemon;

use ArrowWorker\Component\Worker;

use ArrowWorker\Library\Coroutine;
use ArrowWorker\Library\Process;

class ArrowDaemon extends Worker
{

    /**
     *
     */
    const MODULE_NAME = 'Worker';

    /**
     * process life time
     */
    const LIFE_CYCLE = 60;

    /**
     * concurrence coroutine
     */
    const COROUTINE_QUANTITY = 3;

    /**
     * default process name
     */
    const PROCESS_NAME = 'unnamed';

    /**
     * 是否退出 标识
     * @var bool
     */
    private $terminateFlag = false;

    /**
     * 任务数量
     * @var int
     */
    private $jobNum = 0;

    /**
     * 任务map
     * @var array
     */
    private  $jobs = [];

    /**
     * 任务进程 ID map(不带管道消费的进程)
     * @var array
     */
    private $pidMap = [];

    private $execCount = 0;

    /**
     * @var Component;
     */
    private $component;


    /**
     * ArrowDaemon constructor.
     *
     * @param array $config
     */
    public function __construct( $config )
    {
        parent::__construct($config);
    }

    /**
     * init 单例模式初始化类
     * @author Louis
     *
     * @param $config
     *
     * @return ArrowDaemon
     */
    public static function Init( $config ) : self
    {
        self::$user = $config['user']  ?? 'root';
        self::$group = $config['group'] ?? 'root';

        if ( !self::$instance )
        {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /**
     * setSignalHandler 进程信号处理设置
     * @author Louis
     *
     * @param string $type 设置信号类型（子进程/监控进程）
     */
    private function setSignalHandler( string $type = 'parentsQuit' )
    {
        // SIGTSTP have to be ignored on mac os
        switch ( $type )
        {
            case 'workerHandler':
                pcntl_signal(SIGCHLD, SIG_IGN, false);
                pcntl_signal(SIGTERM, SIG_IGN, false);
                pcntl_signal(SIGINT, SIG_IGN, false);
                pcntl_signal(SIGQUIT, SIG_IGN, false);
                pcntl_signal(SIGTSTP, SIG_IGN, false);


                pcntl_signal(SIGALRM, [
                    __CLASS__, "signalHandler",
                ], false);
                pcntl_signal(SIGUSR1, [
                    __CLASS__, "signalHandler",
                ], false);
                break;

            case 'chanHandler':
                pcntl_signal(SIGCHLD, SIG_IGN, false);
                pcntl_signal(SIGTERM, SIG_IGN, false);
                pcntl_signal(SIGINT, SIG_IGN, false);
                pcntl_signal(SIGQUIT, SIG_IGN, false);
                pcntl_signal(SIGUSR1, SIG_IGN, false);
                pcntl_signal(SIGTSTP, SIG_IGN, false);


                pcntl_signal(SIGUSR2, [
                    __CLASS__, "signalHandler",
                ], false);

                break;
            default:
                pcntl_signal(SIGCHLD, [
                    __CLASS__, "signalHandler",
                ], false);
                pcntl_signal(SIGTERM, [
                    __CLASS__, "signalHandler",
                ], false);
                pcntl_signal(SIGINT, [
                    __CLASS__, "signalHandler",
                ], false);
                pcntl_signal(SIGQUIT, [
                    __CLASS__, "signalHandler",
                ], false);
                pcntl_signal(SIGUSR2, [
                    __CLASS__, "signalHandler",
                ], false);
                pcntl_signal(SIGTSTP, SIG_IGN, false);

        }
    }


    /**
     * signalHandler 进程信号处理
     * @author Louis
     *
     * @param int $signal
     *
     * @return void
     */
    public function signalHandler( int $signal )
    {
        //Log::Dump(static::MODULE_NAME . "got a signal {$signal} : " . Process::SignalName($signal));
        switch ( $signal )
        {
            case SIGUSR1:
            case SIGALRM:
                $this->terminateFlag = true;
                break;
            case SIGTERM:
            case SIGHUP:
            case SIGINT:
            case SIGQUIT:
                $this->terminateFlag = true;
                break;
            case SIGUSR2:
                //剩余队列消费专用
                $this->terminateFlag = true;
                break;
            default:
                return;
        }

    }


    /**
     * setProcessName  进程名称设置
     * @author Louis
     *
     * @param string $proName
     */
    private function setProcessName( string $proName )
    {
        Process::SetName(Daemon::$identity . '_Worker_' . $proName);
    }

    /**
     * @param int $processGroupId
     * @param int $lifecycle
     */
    private function setAlarm(int $processGroupId, int $lifecycle )
    {
        Process::SetAlarm(mt_rand(($processGroupId+1)*$lifecycle, ($processGroupId+2)*$lifecycle));
    }


    /**
     * start 挂载信号处理、生成任务worker、开始worker监控
     * @author Louis
     */
    public function Start()
    {

        $this->jobNum = count($this->jobs, 0);

        if ( $this->jobNum == 0 )
        {
            Log::Dump('please add one task at least.', Log::TYPE_WARNING, self::MODULE_NAME,);
            $this->exitMonitor();
        }
        $this->setSignalHandler('monitorHandler');
        $this->forkWorkers();
        $this->startMonitor();
    }

    /**
     * @param int $headGroupId
     */
    private function exitWorkers(int $headGroupId)
    {
        foreach ( $this->pidMap as $pid => $groupId )
        {
            if ( $groupId != $headGroupId )
            {
                continue;
            }
            
            if ( !Process::Kill($pid, SIGUSR1) )
            {
                Process::Kill($pid, SIGUSR1);
            }
            usleep(10000);
        }
    }


    /**
     * exitWorkers 开启worker监控
     * @author Louis
     */
    private function startMonitor()
    {
        while ( 1 )
        {
            if ( $this->terminateFlag )
            {
                $toBeExitedGroupId = $this->calcToBeExitedGroup();
                
                //给工作进程发送退出信号
                $this->exitWorkers($toBeExitedGroupId);
                //等待进程退出
                $this->waitToBeExitedProcess($toBeExitedGroupId);

                if( 0==count($this->pidMap) )
                {
                    //退出监控进程相关操作
                    $this->exitMonitor();
                }
                else
                {
                    continue ;
                }

            }

            pcntl_signal_dispatch();

            $status = 0;
            //returns the process ID of the child which exited, -1 on error or zero if WNOHANG was provided as an option (on wait3-available systems) and no child was available
            $pid = Process::Wait($status);
            pcntl_signal_dispatch();
            $this->handleExited($pid, $status, false);

        }
    }

    /**
     * @return int
     */
    private function calcToBeExitedGroup() : int
    {
        $groups = array_unique(array_values($this->pidMap));
        if( 0==count($groups) )
        {
            return 0;
        }
        sort($groups);
        return (int)$groups[0];
    }


    /**
     * @param  int $groupId
     */
    private function waitToBeExitedProcess(int $groupId)
    {
        $leftProcessCount = 0;
        foreach ($this->pidMap as $pid=>$gid)
        {
            if( $gid==$groupId )
            {
                $leftProcessCount++;
            }
        }

        for($i=0; $i<$leftProcessCount; $i++)
        {
            $status = 0;
            RE_WAIT:
            $pid = Process::Wait($status, WUNTRACED);
            if ( $pid == -1 )
            {
                goto RE_WAIT;
            }

            $this->handleExited($pid, $status, $this->pidMap[$pid]==$groupId ? true : false);
        }
    }



    /**
     * handleExited 处理退出的进程
     * @author Louis
     *
     * @param int  $pid
     * @param int  $status
     * @param bool $isExit
     */
    private function handleExited( int $pid, int $status, bool $isExit = true )
    {
        if ( $pid < 0 )
        {
            return;
        }

        $taskId = $this->pidMap[ $pid ];
        unset($this->pidMap[ $pid ]);

        Log::Dump( "{$this->jobs[ $taskId ]["processName"]}({$pid}) exited at status {$status}", Log::TYPE_DEBUG, self::MODULE_NAME);
        usleep(0==$status ? 10 : 10000 );

        //监控进程收到退出信号时则无需开启新的worker
        if ( !$isExit )
        {
            $this->forkOneWorker($taskId);
        }

    }


    /**
     */
    private function forkWorkers()
    {
        for ( $i = 0; $i < $this->jobNum; $i++ )
        {
            for($j=0; $j<$this->jobs[$i]['processQuantity']; $j++)
            {
                $this->forkOneWorker($i);
            }
        }
    }


    /**
     *
     * @param int $taskId
     */
    private function forkOneWorker( int $taskId )
    {
        $pid = Process::Fork();

        if ( $pid > 0 )
        {
            $this->pidMap[ $pid ] = $taskId;
        }
        else if ( $pid == 0 )
        {
            $this->runWorker($taskId, self::LIFE_CYCLE);
        }
        else
        {
            sleep(1);
        }
    }


    /**
     *
     * @param int $index
     * @param int $lifecycle
     */
    private function runWorker( int $index, int $lifecycle )
    {
        Log::Dump('starting ' . $this->jobs[ $index ]['processName'] . '(' . Process::Id() . ')', Log::TYPE_DEBUG, self::MODULE_NAME );
        $this->setSignalHandler('workerHandler');
        $this->setAlarm($index,$lifecycle);
        $this->setProcessName($this->jobs[ $index ]['processName']);
        $this->initComponent();
        Process::SetExecGroupUser(self::$group, self::$user);
        Coroutine::enable();
        Coroutine::Create(function () use ( $index )
        {
            $this->component->InitPool($this->jobs[ $index ]['components']);
        });
        Coroutine::Wait();
        $this->runProcessTask($index);
    }

    private function initComponent()
    {
        $this->component = Component::Init(App::TYPE_WORKER );
    }

    /**
     * @param int $index
     */
    private function runProcessTask( int $index )
    {
        $timeStart = time();

        Log::Dump("{$this->jobs[ $index ]['processName']} started.",Log::TYPE_DEBUG, self::MODULE_NAME);

        while ( $this->jobs[ $index ]['coCount'] < $this->jobs[ $index ]['coQuantity'] )
        {
            Coroutine::Create(function () use ( $index, $timeStart)
            {
                while ( true )
                {
	                pcntl_signal_dispatch();
	
	                Log::Init();
                    if ( isset($this->jobs[ $index ]['argv']) )
                    {
                        $result = call_user_func_array($this->jobs[ $index ]['function'], $this->jobs[ $index ]['argv']);
                    }
                    else
                    {
                        $result = call_user_func($this->jobs[ $index ]['function']);
                    }
                    $this->execCount++;

                    //release components resource after finish one work
                    $this->component->Release();
                    
	                pcntl_signal_dispatch();
	
	                if ( $this->terminateFlag && false==(bool)$result )
                    {
                    	break;
                    }
                }

            });
            $this->jobs[ $index ]['coCount']++;
        }

        Coroutine::Wait();
        $execTimeSpan = time() - $timeStart;
        Log::Dump("{$this->jobs[ $index ]['processName']} finished {$this->execCount} times / {$execTimeSpan} S.", Log::TYPE_DEBUG, self::MODULE_NAME );
        exit(0);

    }

    /**
     * 
     */
    private function exitMonitor()
    {
        Log::Dump( "exited", Log::TYPE_DEBUG, self::MODULE_NAME);
        exit(0);
    }

    /**
     * addTask 添加任务及相关属性
     * @author Louis
     *
     * @param array $job
     */
    public function AddTask( $job = [] )
    {

        if ( !isset($job['function']) || empty($job['function']) )
        {
            Log::DumpExit("one Task at least is needed ");
        }

        $job['coCount']    = 0;
        $job['coQuantity'] = ( isset($job['coQuantity']) && (int)$job['coQuantity'] > 0 ) ? (int)$job['coQuantity'] :
            self::COROUTINE_QUANTITY;
        $job['processQuantity'] = ( isset($job['processQuantity']) && (int)$job['processQuantity'] > 0 ) ? (int)$job['processQuantity'] :
            1;
        $job['processName'] = ( isset($job['name']) && !empty($job['name']) ) ? $job['name'] : self::PROCESS_NAME;
        $job['components'] = isset($job['components']) && is_array($job['components']) ? $job['components'] : [];

        $this->jobs[] = $job;
    }

}

