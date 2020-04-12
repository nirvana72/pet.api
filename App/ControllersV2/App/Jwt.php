<?php
namespace App\ControllersV2\App;
use PhpBoot\DI\Traits\EnableDIAnnotations;
// use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
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
            return ['ret' => -1, 'msg' => 'token error at refresh'];
        }        
        
        //验证token合法性
        if (!$token->verify($signer, $this->jwtKey)) {
            return ['ret' => -1, 'msg' => 'token verify at refresh'];
        }
       
        //验证是否已经过期
        if ($token->isExpired()) {
            return ['ret' => -1, 'msg' => 'token expired at refresh'];
        }

        $uid = $token->getClaim('uid');
        $func = new \App\Utils\Func();
        
        $result = $func->createToken($uid, $this->jwtKey, $this->host);
        $result['ret'] = 1;
        $result['msg'] = 'success';
        return $result;
    }
} 