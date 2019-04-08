<?php
/**
 * User: louis
 * Date: 17-10-20
 * Time: 上午12:51
 */

namespace ArrowWorker\Web;

use ArrowWorker\Config;
use ArrowWorker\Log;

/**
 * Class Router
 * @package ArrowWorker
 */
class Router
{
	/**
	 * 默认控制器/方法
	 */
	const DEFAULT_CONTROLLER  = 'Index';

	const DEFAULT_METHOD = 'index';

	const CONTROLLER_NAMESPACE = '\\'.APP_DIR.'\\'.APP_CONTROLLER_DIR.'\\';

	private static $_restApiConfig = [];

	private static $_pregAlias = [];

	public static function Init()
    {
        self::_loadRestConfig();
        self::_analyseUri();
    }

    private static function _loadRestConfig()
    {
        $config = Config::Get('Rest');
        if( false===$config )
        {
            Log::Warning("Load rest api configuration failed");
            return ;
        }
        if( !is_array($config) )
        {
            Log::Warning(" rest api configuration format is incorrect.");
            return ;
        }

        foreach ($config as $serverNames=>$restMap)
        {
            $serverNameArray = explode(',', $serverNames);
            foreach ( $serverNameArray as $serverName)
            {
                static::$_restApiConfig[trim($serverName)] = $restMap;
            }
        }

    }

    private static function _analyseUri()
    {
        foreach (static::$_restApiConfig as $serverName=>$restMap)
        {
            foreach ($restMap as $uri=>$alias)
            {
                $nodes    = explode('/', $uri);
                $match    = preg_replace(['/:\w+/','/\//'], ['[a-zA-Z0-9_-]+','\\/'], $uri);
                $colonPos = strpos($uri, ':');
                $key      = (false===$colonPos) ? $uri : substr($uri, 0, $colonPos-1);
                $params   = [];
                foreach ($nodes as $index=>$param)
                {
                    if( false===strpos($param, ':') )
                    {
                        continue;
                    }
                    $params[$index] = str_replace(':', '', $param);
                }
                static::$_pregAlias[$serverName][$key]["/^{$match}$/"] = [
                    'uri'    => $uri,
                    'params' => $params
                ];
            }
        }

    }

    public static function _getRestUriKey() : string
    {
        $uri        = Request::Server('request_uri');
        $nodes      = explode('/', $uri);
        $nodeLen    = count($nodes);
        $serverName = Request::Header('host');

        for($i=$nodeLen; $i>1; $i--)
        {
            $key = '/'.implode('/', array_slice($nodes,1, $i-1));
            if( !isset(static::$_pregAlias[$serverName][$key]) )
            {
                continue ;
            }

            $nodeMap = static::$_pregAlias[$serverName][$key];
            foreach ( $nodeMap as $match=>$eachNode )
            {
                $isMatched = preg_match($match, $uri);
                if( false===$isMatched || $isMatched===0)
                {
                    continue ;
                }

                //获取对应参数值
                $params = [];
                foreach ($eachNode['params'] as $index=>$param)
                {
                    $params[$param] = $nodes[$index];
                }
                Request::SetParams($params);
                return $eachNode['uri'];
            }
        }
        return '';
    }


	/**
	 * Go 返回要调用的控制器和方法
	 */
	public static function Go()
    {
        if( static::_restRouter() )
        {
            return ;
        }

        if( static::_pathRouter() )
        {
            return ;
        }
        if( static::_routeToDefault() )
        {
            return ;
        }
        static::_logAndResponse("request does not match any router");
    }

    private static function _restRouter()
    {
        $key        = static::_getRestUriKey();
        $method     = strtolower(Request::Method());
        $serverName = Request::Header('host');

        if( empty($key) )
        {
            return false;
        }

        if( !isset(static::$_restApiConfig[$serverName][$key][$method]) )
        {
            return false;
        }

        list($class, $function) = explode('::', static::$_restApiConfig[$serverName][$key][$method]);
        $class = self::CONTROLLER_NAMESPACE.$class;
        return static::_routeToFunction($class, $function);
    }

    private static function _pathRouter()
    {
        $uri      = Request::Server('request_uri');
        $pathInfo = explode('/', $uri);
        $pathLen  = count($pathInfo);

        if( $pathLen<3 )
        {
            return false;
        }

        if( $pathLen==4 && $pathInfo[1]!='' && $pathInfo[2]!='' && $pathInfo[3]!='' )
        {
            $class = self::CONTROLLER_NAMESPACE.$pathInfo[1].'\\'.$pathInfo[2];
            return static::_routeToFunction($class, $pathInfo[3]);
        }

        if( $pathLen>=3 && $pathInfo[1]!='' && $pathInfo[2]!='' )
        {
            $class = self::CONTROLLER_NAMESPACE.$pathInfo[1];
            return static::_routeToFunction($class, $pathInfo[2]);
        }

        return false;
    }

    private static function _routeToDefault()
    {
        $class = self::CONTROLLER_NAMESPACE.DEFAULT_CONTROLLER;
        return static::_routeToFunction($class, DEFAULT_METHOD);
    }

    private static function _routeToFunction(string $class, string $function)
    {
        if( !class_exists($class) )
        {
            static::_logAndResponse("class : {$class} does not exists.");
        }

        $controller = new $class;
        if( !method_exists($controller, $function) )
        {
            static::_logAndResponse("function : {$class}->{$function} does not exists.");
        }
        $controller->$function();
        return true;
    }

    private static function _logAndResponse(string $msg)
    {
        Log::Warning($msg);
        Response::Write($msg);
    }


}