<?php
//@see PbpBoot\Application::createByDefault
return [
    //The host (name or ip) serving the API, used by swagger docgen.
    'host' => 'api.mu78.com',

    //The App's name, default is "App", use by \Monolog\Logger as the logging channel
    'App.name'  => 'phpboot-example',

    //The prefix of api uri path, default is "/"
    'App.uriPrefix' => '/v2/',

    //DB.* are the params for PDO::__construct, @see http://php.net/manual/en/pdo.construct.php
    'DB.connection'=> 'mysql:dbname=pet;host=mysql;',
    'DB.username'=> 'root',
    'DB.password'=> '123456',
    'DB.options' => [],

    //自己定义配置
    'my.jwtKey' => 'wegrukjhgfe',
    'my.environment' => 'production', //production

    /************************************************************************************
    如果要将系统缓存改成文件方式, 取消下面的注释。默认系统缓存是 APC
    注意这里的系统缓存指路由、依赖注入方式等信息的缓存, 而不是业务接口返回数据的缓存。
    所以这里不要使用 redis 等远程缓存
     ************************************************************************************/
    \Doctrine\Common\Cache\Cache::class => \DI\object(\Doctrine\Common\Cache\FilesystemCache::class)
        ->constructorParameter('directory', $_SERVER['DOCUMENT_ROOT'] . '/../cache/'), //sys_get_temp_dir()

    /************************************************************************************
    若需要在业务中使用 Redis,请打开此注释, 以便RedisCache可以通过依赖注入被 Controller 使用

    \Doctrine\Common\Cache\RedisCache::class => \DI\object()
        ->method('setRedis', \DI\factory(function(){
            $redis = new \Redis();
            $redis->connect('127.0.0.1', 6379);
            return $redis;
        })),

    // 并在需要的地方,通过依赖注入 redis 实例, 如:
    // /**
    //  * @inject
    //  * @var \Doctrine\Common\Cache\RedisCache
    //  */
    //  private $redis;

     /************************************************************************************/

    //默认日志路径在此修改
  /*  日志等级
    DEBUG (100): 详细的debug信息。
    INFO (200): 关键事件。
    NOTICE (250): 普通但是重要的事件。
    WARNING (300): 出现非错误的异常。
    ERROR (400): 运行时错误，但是不需要立刻处理。
    CRITICA (500): 严重错误。
    EMERGENCY (600): 系统不可用*/
    'defaultLoggerStream' => \DI\object(\Monolog\Handler\StreamHandler::class)
        ->constructor($_SERVER['DOCUMENT_ROOT'] . '/logs/' . date("Y-m-d") . '.log', \Monolog\Logger::ERROR),

    \Psr\Log\LoggerInterface::class => \DI\object(\Monolog\Logger::class)
        ->constructor('phpboot')->method('pushHandler',\DI\get('defaultLoggerStream')),

    //异常输出类
    \PhpBoot\Controller\ExceptionRenderer::class =>
        \DI\object(\App\Utils\ExceptionRendererV2::class),

    /* 
    \App\Hooks\BasicAuth::class => \DI\object()
            ->property('username', 'test')
            ->property('password', 'test'),
        */
/*
    \App\Interfaces\BooksInterface::class => \DI\object(\PhpBoot\RPC\RpcProxy::class)
        ->constructorParameter('interface', \App\Interfaces\BooksInterface::class)
        ->constructorParameter('prefix', 'http://example.phpboot.org/')
		*/
];