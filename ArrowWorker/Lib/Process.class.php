<?php
/**
 * By yubin at 2019-10-18 18:29.
 */

namespace ArrowWorker\Lib;

use ArrowWorker\Log;


/**
 * Class Process
 * @package ArrowWorker\Lib
 */
class Process
{

    /**
     *
     */
    const LOG_PREFIX = '[ Process ] ';

    const SIGNAL_COMMON_MAP = [
        's1'  => 'SIGHUP',
        's2'  => 'SIGINT',    //Ctrl-C
        's3'  => 'SIGQUIT',
        's4'  => 'SIGILL',
        's5'  => 'SIGTRAP',
        's6'  => 'SIGIOT',
        's8'  => 'SIGFPE',
        's9'  => 'SIGKILL',
        's13' => 'SIGPIPE',
        's14' => 'SIGALRM',
        's15' => 'SIGTERM',
        's21' => 'SIGTTIN',
        's22' => 'SIGTTOU',
    ];

    const SIGNAL_MAC_MAP = [
        's10' => 'SIGBUS',
        's30' => 'SIGUSR1',
        's31' => 'SIGUSR2',
        's20' => 'SIGCHLD',
        's19' => 'SIGCONT',
        's17' => 'SIGSTOP',
        's18' => 'SIGTSTP',
        's16' => 'SIGURG',
    ];

    const SIGNAL_LINUX_MAP = [
        's7'  => 'SIGBUS',
        's10' => 'SIGUSR1',
        's12' => 'SIGUSR2',
        's17' => 'SIGCHLD',
        's18' => 'SIGCONT',
        's19' => 'SIGSTOP',
        's20' => 'SIGTSTP',
        's23' => 'SIGURG',
    ];

    private static $_signalMap = [];

    /**
     * @var array
     */
    private static $_killNotificationPidMap = [];

    /**
     * @param string $name
     */
    public static function SetName( string $name)
    {
        if( PHP_OS=='Darwin')
        {
            return ;
        }

        if(function_exists('cli_set_process_title'))
        {
            @cli_set_process_title($name);
        }
        if(extension_loaded('proctitle') && function_exists('setproctitle'))
        {
            @setproctitle($name);
        }
    }

    /**
     * @return int
     */
    public static function Id() : int
    {
        return posix_getpid();
    }

    /**
     * @return int
     */
    public static function Fork()
    {
        return pcntl_fork();
    }

    /**
     * @param int $seconds
     */
    public static function SetAlarm( int $seconds)
    {
        pcntl_alarm( $seconds );
    }

    /**
     * @param int $status
     * @param int $options
     * @return int
     */
    public static function Wait( int &$status, int $options=WUNTRACED) : int
    {
        return pcntl_wait($status, $options);
    }

    /**
     * @param int $pid
     * @param int $signal
     * @param bool $isForceNotify
     * @return bool
     */
    public static function Kill( int $pid, int $signal, bool $isForceNotify=false) : bool
    {
        if( $isForceNotify )
        {
            goto KILL;
        }

        if( self::IsKillNotified($pid.$signal) )
        {
            return true;
        }

        KILL:
        if( posix_kill( $pid, $signal ) )
        {
            self::$_killNotificationPidMap[] = $pid.$signal;
            return true;
        }
        return false;
    }

    /**
     * @param string $pidSignal
     * @return bool
     */
    public static function IsKillNotified( string $pidSignal)
    {
        return in_array($pidSignal, self::$_killNotificationPidMap);
    }

    public static function SignalName(int $signal) : string
    {
        if( 0==count(self::$_signalMap) )
        {
            self::$_signalMap =  PHP_OS=='Darwin' ?
                array_merge(self::SIGNAL_COMMON_MAP, self::SIGNAL_MAC_MAP) :
                array_merge(self::SIGNAL_COMMON_MAP, self::SIGNAL_LINUX_MAP);
        }

        $key = 's'.$signal;
        if( !isset( self::$_signalMap[$key] ) )
        {
            return 'unknown';
        }
        return self::$_signalMap[$key];
    }

    /**
     * @param int $seconds
     */
    public static function Sleep( int $seconds)
    {
        sleep($seconds);
    }

    /**
     * @param string $group
     * @param string $user
     */
    public static function SetExecGroupUser( string $group, string $user)
    {
        $user  = posix_getpwnam( $user );
        $group = posix_getgrnam( $group );

        if( !$user || !$group )
        {
            Log::Dump(self::LOG_PREFIX. ' '.__FUNCTION__.", posix_getpwnam({$user})/posix_getgrnam({$group}) failed！");
        }

        if( !posix_setuid($user['uid']) || !posix_setgid($group['gid']) )
        {
            Log::Dump(self::LOG_PREFIX. ' '.__FUNCTION__.",  posix_setuid({$user['uid']})/posix_setgid({$group['gid']}) failed！");
        }
    }

}