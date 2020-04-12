<?php
namespace App\Hooks;
use PhpBoot\DI\Traits\EnableDIAnnotations;
use PhpBoot\Controller\HookInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
/**
 * 简单登录校验
 *
 * 实现了 Basic Authorization
 * @package App\Hooks
 */
class BasicAuth implements HookInterface
{
    use EnableDIAnnotations; //启用通过@inject标记注入依赖
    /**
     * @inject my.environment
     * @var string
     */
    private $environment;

    /**
     * @inject my.jwtKey
     * @var string
     */
    private $jwtKey;    

    /**
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    public function handle(Request $request, callable $next) { 
        if($this->environment === 'develop'){
            return $next($request); 
        }
        
        $Bearer = $request->headers->get('Authorization');
       
        $Bearer = substr($Bearer, 7); // Bearer 
        
        $signer = new \Lcobucci\JWT\Signer\Hmac\Sha256();

        $isAdmin = $request->headers->get('Admin') === '1';
        
        //解析token
        $parser = new \Lcobucci\JWT\Parser();
            
        $token = null;
        try {
            $token = $parser->parse($Bearer);
        } catch (\Exception $e) {
            \PhpBoot\abort('登录信息过期,请重新登录[error]');
        }
        
        //验证token合法性
        $key = $this->jwtKey;
        if ($isAdmin == true) { $key = $this->jwtKey . 'admin'; }
        if (!$token->verify($signer, $key)) {
            \PhpBoot\abort('登录信息过期,请重新登录[verify]');
        }
       
        //验证是否已经过期
        if ($token->isExpired()) {
            \PhpBoot\abort("token_expired_verify");
        }

        // 取出解析后uid, 给后续controller 用
        // $uid = $token->getClaim('uid');
        // $request->headers->set('uid',  $uid);

        return $next($request); 
    }
}