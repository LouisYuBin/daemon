<?php
/**
 * User: Louis
 * Date: 2016/8/3 12:02
 * Update Records:
 *      2017-07-24 by Louis
 */

namespace ArrowWorker;

/**
 * Class Config
 * @package ArrowWorker
 */
class Config
{
    /**
     * app class map file
     * @var string
     */
    public static $AppFileMap  = 'alias';

    /**
     * 配置文件路径
     * @var string
     */
    private static $path          = '';

    /**
     * 框架默认配置
     * @var array
     */
    private static $AppConfig     = [];

    /**
     * 用户自定义配置
     * @var array
     */
    private static $ExtraConfig   = [];

    /**
     * 配置文件记录
     * @var array
     */
    private static $configFileMap = [];

    /**
     * 框架配置文件中用户配置文件数据对应key
     * @var string
     */
    private static $extraConfKey  = 'user';

    /**
     * 配置文件后缀
     * @var array
     */
    private static $configExt     = '.php';

    /**
     * 框架配置文件是否加载
     * @var bool
     */
    private static $AppConfigLoaded   = false;

    /**
     * 用户自定义配置文件是否加载
     * @var bool
     */
    private static $ExtraConfigLoaded = false;


	/**
	 * _init 初始化(配置文件路径)
     * @return void
	 */
	public static function Init(string $configFilePath='') : void
    {
        if( empty(self::$path) && empty($configFilePath) )
        {
            self::$path = APP_PATH . DIRECTORY_SEPARATOR . APP_CONFIG_FOLDER . DIRECTORY_SEPARATOR;
        }
        else if( !empty($configFilePath) )
        {
            self::$path = $configFilePath;
        }
    }

    /**
     * App
     * @author Louis
     * @param string $key
     * @param string $AppConfig
     * @return array|mixed
     */
    public static function App(string $key='',string $AppConfig=APP_CONFIG_FILE )
    {
        if( !static::$AppConfigLoaded )
        {
            static::$AppConfig = self::Load( $AppConfig );
            static::$AppConfigLoaded = true;
        }

        return ( !empty($key) && isset(static::$AppConfig[$key]) ) ? static::$AppConfig[$key] : static::$AppConfig;
    }


	/**
	 * Extra 加载除默认配置文件以外的配置文件
	 * @param string $key
	 * @return mixed
	 */
	public static function Extra(string $key='' ) : mixed
    {
        if( !static::$ExtraConfigLoaded )
        {
            if( isset( static::$AppConfig[static::$extraConfKey] ) && count( static::$AppConfig[static::$extraConfKey] )>0 )
            {
                foreach( static::$AppConfig[static::$extraConfKey] as $eachExtraConfig )
                {
                    static::$ExtraConfig = array_merge( static::$ExtraConfig, static::Load( $eachExtraConfig ) );
                }
            }
            static::$ExtraConfigLoaded = true;
        }

        return ( !empty($key) && isset(static::$ExtraConfig[$key]) ) ? static::$ExtraConfig[$key] : static::$ExtraConfig;
    }

	/**
	 * Load 加载特定的配置文件
	 * @param string $fileName
	 * @return mixed
	 */
	public static function Load(string $fileName )
    {
        static::Init();
        if( isset( self::$configFileMap[$fileName] ) )
        {
            return self::$configFileMap[$fileName];
        }

        $configFilePath = self::$path.$fileName.self::$configExt;
        if( !file_exists($configFilePath) )
        {
            throw new \Exception( "Config File : {$configFilePath} does not exists.");
        }
        self::$configFileMap[$fileName] = require( $configFilePath );
        return self::$configFileMap[$fileName];
    }

}
