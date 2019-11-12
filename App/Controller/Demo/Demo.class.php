<?php
/**
 * User: Louis
 * Date: 2016/8/2
 * Time: 10:35
 */

namespace App\Controller\Demo;

use App\Model\ArrowWorker;
use ArrowWorker\Client\Tcp\Pool;
use ArrowWorker\Client\Ws\Pool as WsPool;
use ArrowWorker\Log;
use ArrowWorker\Chan;
use ArrowWorker\Lib\Coroutine;


class Demo
{

    public function Demo($argv=0)
    {

        $writeResult = Chan::Get()->Write("app".mt_rand(1,1000));
        Log::Info($writeResult);


        ArrowWorker::GetOne();
        ArrowWorker::GetList();

        //Coroutine::Sleep(1);
        //WsPool::GetConnection()->Push(mt_rand(10000,99999));
        //Pool::GetConnection()->Send(mt_rand(10000,99999));
        return false;
    }

    public function channelApp()
    {

        $result  = Chan::Get()->Read();
        if( !$result )
        {
            return false;
        }

        ArrowWorker::GetOne();

        Chan::Get('arrow')->Write($result);
        return true;
    }

    public function channelArrow()
    {
        $channel = Chan::Get('arrow');
        $result  = $channel->Read();
        if( !$result )
        {
            return false;
        }
        ArrowWorker::GetList();

        Chan::Get('test')->Write($result);
        return true;
    }

	public function channelTest()
	{
	    $result  = Chan::Get('test')->Read();
		if( !$result )
		{
			return false;
		}

        //ArrowWorker::GetOne();

        return true;
	}

}