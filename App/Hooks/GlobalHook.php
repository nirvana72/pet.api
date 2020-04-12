<?php
namespace App\Hooks;
use PhpBoot\DI\Traits\EnableDIAnnotations;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use PhpBoot\DB\DB;

/**
 * 全局勾子
 * 实现权限验证
 */
class GlobalHook
{
    use EnableDIAnnotations; //启用通过@inject标记注入依赖

     /**
     * @inject
     * @var DB
     */
    private $db;
    /**
     * @inject my.environment
     * @var string
     */
    private $environment;

     /**
     * Handle an incoming request.
     *
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, \Closure $next)
    {
        if($this->environment === 'develop'){
            return $next($request); 
        }

        $log = [
            'url'   => $request->getPathInfo(),
            'method'=> $request->getMethod(),
            'ip'    => $request->getClientIps()[0],
            'uid'   => $request->headers->get('ClientUid'),  // 本应取jwt解析的uid, 但此处先于解析jwt执行，所以暂用非信任的客户端Client-Uid
            'device'   => $request->headers->get('ClientDevice')
        ];
        $log['method'] = strtolower($log['method']);
        $log['url'] = strtolower(preg_replace("/\/[0-9]+/","/{int}",$log['url']));
        $log['uid'] = is_null ($log['uid'])? -1: $log['uid'];

        $this->db->insertInto('log_api_call')->values($log)->exec();
        return $next($request);
    }
}