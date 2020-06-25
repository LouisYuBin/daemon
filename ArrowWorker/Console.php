<?php

namespace ArrowWorker;

use ArrowWorker\Library\Process;
use ArrowWorker\Library\System\LoadAverage;
use ArrowWorker\Log\Log;

/**
 * Class Console
 * @package ArrowWorker
 */
class Console
{

    /**
     * @var Container $container
     */
    private $container;

    /**
     * @var array
     */
    private $argv = [];

    /**
     * @var string
     */
    private $entryFile = '';

    /**
     * @var string
     */
    private $runType = '';

    /**
     * @var string
     */
    private $runEnv = '';

    /**
     * @var string
     */
    private $actionName = '';

    /**
     * @var bool
     */
    private $isDebug = false;

    /**
     * @var array
     */
    private $actionAlias = [
        'stop'    => 'stop',
        'start'   => 'start',
        'status'  => 'getStatus',
        'restart' => 'restart'
    ];


    /**
     * Console constructor.
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->checkStartEnv();
        $this->parseCommandArgv();
        $this->checkExtension();
        Environment::setType($this->GetEnv());
        $this->container->Get(Config::class);
    }

    /**
     * @return bool
     */
    private function stop(): bool
    {
        $pid = Daemon::GetPid();
        if (0 === $pid) {
            Log::Hint('Arrow is not running.');
            return false;
        }

        for ($i = 1; $i > 0; $i++) {
            if ($i === 1) {
                if (Process::Kill($pid, SIGTERM)) {
                    echo('Arrow stopping');
                } else {
                    Log::Hint('Arrow is not running.');
                    return false;
                }
            } else {
                if (!Process::Kill($pid, SIGTERM, true)) {
                    Log::Hint('stopped successfully.');
                    return true;
                }

                echo '.';
                sleep(1);

            }
        }
        return false;
    }

    /**
     *
     * @return void
     */
    public function Run(): void
    {
        $action = $this->actionAlias[$this->actionName] ?? null;
        if (is_null($this->actionAlias[$this->actionName])) {
            Log::Hint("Oops! Unknown operation. please use \"php {$this->entryFile} start/stop/status/restart\" to start/stop/restart the service");
            return;
        }

        $this->$action();
    }

    /**
     *
     */
    private function start(): void
    {
        Log::Hint("starting ...{$this->runType}({$this->runEnv})");
        $this->container->Get(App::class, [$this->container])->Run($this->runType, $this->isDebug);
    }

    /**
     *
     */
    private function getStatus()
    {
        $keyword = PHP_OS === 'Darwin' ? $this->entryFile : Daemon::APP_NAME . '_' . Daemon::GetPid();
        $commend = "ps -e -o 'user,pid,ppid,pcpu,%mem,args' | grep {$keyword}";
        $output  = 'user | pid | ppid | cpu usage | memory usage | process name' . PHP_EOL;
        $results = LoadAverage::Exec($commend);
        $output  .= implode(PHP_EOL, $results);
        echo $output . PHP_EOL;
    }

    /**
     *
     */
    private function restart()
    {
        if ($this->stop()) {
            $this->start();
        }
    }

    /**
     *
     */
    private function parseCommandArgv()
    {
        global $argv;
        $this->argv = $argv;
        if (count($this->argv) < 2) {
            Log::DumpExit('Parameter needed');
        }

        [
            $this->entryFile,
            $this->actionName,
        ] = $argv;

        $this->runEnv  = $argv[3] ?? 'dev';
        $this->runType = $argv[2] ?? 'server';
        $this->isDebug = isset($argv[4]) && 'true' === trim($argv[4]);
    }

    /**
     *
     */
    private function checkStartEnv() : void
    {
        if (php_sapi_name() !== "cli") {
            Log::DumpExit("Arrow hint : only run in command line mode");
        }
    }

    /**
     *
     */
    private function checkExtension()
    {
        if (!extension_loaded('swoole')) {
            Log::DumpExit('extension swoole is not installed/loaded.');
        }

        if (!extension_loaded('sysvmsg')) {
            Log::DumpExit('extension sysvmsg is not installed/loaded.');
        }

        if ((int)str_replace('.', '', (new \ReflectionExtension('swoole'))->getVersion()) < 400) {
            Log::DumpExit('swoole version must be newer than 4.0 .');
        }

    }

    /**
     * @return bool
     */
    public function IsDebug() : bool
    {
        return $this->isDebug;
    }

    /**
     * @return string
     */
    public function GetEnv() : string
    {
        return ucfirst($this->runEnv);
    }

}
