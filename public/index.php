<?php
use PhpBoot\Docgen\Swagger\Swagger;
use PhpBoot\Docgen\Swagger\SwaggerProvider;
use PhpBoot\Application;
use PhpBoot\Docgen\Swagger\Schemas\ExternalDocumentationObject;

// ini_set('date.timezone','Asia/Shanghai');

//------------------------------------------------------
require __DIR__.'/../vendor/autoload.php';

// 加载配置
$app = Application::createByDefault(
    __DIR__.'/../config/config.php'
);
//------------------------------------------------------
// 全局勾子
$app->setGlobalHooks([
    // 支持跨域访问
    \PhpBoot\Controller\Hooks\Cors::class , 
    // 访问日志
    \App\Hooks\GlobalHook::class
]);

//------------------------------------------------------
//接口文档自动导出功能, 如果要关闭此功能, 只需注释掉这块代码
//{{
SwaggerProvider::register($app, function(Swagger $swagger)use($app){
    $swagger->schemes = ['https'];
    $swagger->host = $app->get('host');
    $swagger->info->title = 'PhpBoot 示例';
    $swagger->info->description = "此文档由 PbpBoot 生成 swagger 格式的 json, 再由Swagger UI 渲染成 web。";
    $swagger->externalDocs=new ExternalDocumentationObject();
    $swagger->externalDocs->description = '接口对应代码';
    // $swagger->externalDocs->url = 'http://localhoat:8080/Users.php';
});
//}}


//------------------------------------------------------

$app->loadRoutesFromPath( __DIR__.'/../App/Controllers/App', 'App\\Controllers\\App');
$app->loadRoutesFromPath( __DIR__.'/../App/Controllers/Admin', 'App\\Controllers\\Admin');
$app->loadRoutesFromPath( __DIR__.'/../App/Controllers/Test', 'App\\Controllers\\Test');

//执行请求
$app->dispatch();
