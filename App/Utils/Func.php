<?php
namespace App\Utils;

class Func
{
  public function CheckAccountType($account)
  {
    $regex = [
      'email' => "/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/",
      'mobile' => "/^(13[0-9]|14[0-9]|15[0-9]|166|17[0-9]|18[0-9]|19[8|9])\d{8}$/",
      'account' => "/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]{2,18}$/u"
    ];
    
    if(preg_match($regex['email'], $account)){
      return 'email';
    }   
    if(preg_match($regex['mobile'], $account)){
      return 'mobile';
    }
    if(preg_match($regex['account'], $account)){
      return 'account';
    }
    return '';
  }

  public function createToken($uid, $jwtKey, $host){
    $signer = new \Lcobucci\JWT\Signer\Hmac\Sha256();
    $time = time();
    $token = (new \Lcobucci\JWT\Builder())->setIssuer($host) // Configures the issuer (iss claim)
        ->setAudience($host) // Configures the audience (aud claim)
        ->setId('4f1g23a12aa', true) // Configures the id (jti claim), replicating as a header item
        ->setIssuedAt($time) // Configures the time that the token was issued (iat claim)
        ->setNotBefore($time + 60) // Configures the time that the token can be used (nbf claim)
        ->setExpiration($time + 3600 * 24) // Configures the expiration time of the token (exp claim)
        ->set('uid', $uid) // Configures a new claim, called "uid"
        ->sign($signer, $jwtKey) // creates a signature using "testing" as key
        ->getToken(); // Retrieves the generated token

    $refreshtoken = (new \Lcobucci\JWT\Builder())->setIssuer($host) // Configures the issuer (iss claim)
        ->setAudience($host) // Configures the audience (aud claim)
        ->setId('572hg240482', true) // Configures the id (jti claim), replicating as a header item
        ->setIssuedAt($time) // Configures the time that the token was issued (iat claim)
        ->setNotBefore($time + 60) // Configures the time that the token can be used (nbf claim)
        ->setExpiration($time + 3600 * 24 * 15) // Configures the expiration time of the token (exp claim)
        ->set('uid', $uid) // Configures a new claim, called "uid"
        ->sign($signer, $jwtKey) // creates a signature using "testing" as key
        ->getToken(); // Retrieves the generated token

    $jwt['token'] = (string)$token;
    $jwt['refreshtoken'] = (string)$refreshtoken;
    return $jwt;
  }

  public function ip() {
    //strcasecmp 比较两个字符，不区分大小写。返回0，>0，<0。
    if(getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
        $ip = getenv('HTTP_CLIENT_IP');
    } elseif(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
        $ip = getenv('HTTP_X_FORWARDED_FOR');
    } elseif(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
        $ip = getenv('REMOTE_ADDR');
    } elseif(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    $res =  preg_match ( '/[\d\.]{7,15}/', $ip, $matches ) ? $matches [0] : '';
    return $res;
    //dump(phpinfo());//所有PHP配置信息
  }
  
  // 百度地图
  // http://lbsyun.baidu.com/
  public function address($ip = '') {
    if ($ip === '') { $ip = $this->ip(); }
    $ip = $this->ip();
    $url = 'http://api.map.baidu.com/location/ip?ak=BW7QmkhvYtHD9G5f2nbOA9xCYMLIR22h&ip='.$ip;
    $ipContent = file_get_contents($url);
    $ipContent = json_decode($ipContent,true); 
    return $ipContent;
  }

  public function convertAvatar($avatar) {
    $host = 'https://oss.mu78.com';
    $avatar = intval($avatar);
    $url = "/avatar/{$avatar}.png";
    if($avatar > 100) {
      $groupId = ceil($avatar / 1000) * 1000;
      $url = "/avatar/{$groupId}/{$avatar}.png";
    }
    $url .= "?x-oss-process=style/thumb100_100";
    return $host . $url;
  }
}