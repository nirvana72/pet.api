<?php
namespace App\Controllers\App;
use PhpBoot\DI\Traits\EnableDIAnnotations;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
/**
 * APP - JWT
 *
 * @path /app/jwt
 */
class Jwt
{
    use EnableDIAnnotations; //启用通过@inject标记注入依赖

    /**
     * @inject host
     * @var string
     */
    private $host;

    /**
     * @inject my.jwtKey
     * @var string
     */
    private $jwtKey;

    /**
     *
     * 刷新token
     *
     * @route GET /refresh
     * @param string $refreshToken refreshToken
     */
    public function refresh($refreshToken){    

        $signer = new \Lcobucci\JWT\Signer\Hmac\Sha256();

        //解析token
        $parser = new \Lcobucci\JWT\Parser();
            
        $token = null;
        try {
            $token = $parser->parse($refreshToken);
        } catch (\Exception $e) {
            \PhpBoot\abort("Invalid token - error");
        }        
        
        //验证token合法性
        if (!$token->verify($signer, $this->jwtKey)) {
            \PhpBoot\abort(new UnauthorizedHttpException('jwt-auth', "Invalid token - verify"));
        }
       
        //验证是否已经过期
        if ($token->isExpired()) {
            \PhpBoot\abort(new UnauthorizedHttpException('jwt-auth', "Invalid token - expired"));
        }

        $uid = $token->getClaim('uid');
        $func = new \App\Utils\Func();
        return $func->createToken($uid, $this->jwtKey, $this->host);
    }
} 