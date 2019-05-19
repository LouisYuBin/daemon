<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 19-3-3
 * Time: 下午6:30
 */

namespace ArrowWorker\Lib\Client;

use ArrowWorker\Log;
use \Swoole\Coroutine\Http\Client;


/**
 * Class WebSocket
 * @package ArrowWorker\Lib\Client
 */
class WebSocket
{
    /**
     * @var null|Client
     */
    private $_instance = null;

    /**
     * @var string
     */
    private $_logName = 'webSocket_client';

    /**
     * WebSocket constructor.
     * @param string $host
     * @param int    $port
     * @param bool $isSsl
     */
    private function __construct(string $host, int $port=80, bool $isSsl=false)
    {
        $this->_instance = new Client($host, $port, $isSsl);
    }

    /**
     * @param string $host
     * @param int    $port
     * @param bool $isSsl;
     * @return WebSocket
     */
    public static function Init(string $host, int $port, bool $isSsl=false)
    {
        return new self($host, $port, $isSsl);
    }

    /**
     * @param string $data
     * @param string $uri
     * @param int $retryTimes
     * @return bool
     */
    public function Push(string $data, string $uri='/', int $retryTimes=3) : bool
    {
        for( $i=0; $i<$retryTimes; $i++)
        {
            if( true==$this->_instance->upgrade( $uri ) )
            {
                break ;
            }
            Log::Error("upgrade failed : {$i}", $this->_logName);
        }

        for ($i=0; $i<$retryTimes; $i++)
        {
            if( true==$this->_instance->push($data) )
            {
                return true;
            }
            Log::Error("push failed : {$i}", $this->_logName);
        }
        return false;
    }

    /**
     * @param float $timeout
     * @return bool|string|\Swoole\WebSocket\Frame
     */
    public function Receive(float $timeout)
    {
        return $this->_instance->recv($timeout);
    }

    /**
     * @return bool
     */
    public function Close()
    {
        return $this->_instance->close();
    }


}