<?php
namespace App\Controllers\App;
use PhpBoot\DI\Traits\EnableDIAnnotations;
use Psr\Log\LoggerInterface;
use PhpBoot\DB\DB;
use App\Hooks\BasicAuth;

/**
 * APP - 用户管理
 *
 * @path /app/users
 */
class Users
{
    use EnableDIAnnotations; //启用通过@inject标记注入依赖

    private $regex = [
         'email' => "/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/"
        ,'mobile' => "/^(13[0-9]|14[0-9]|15[0-9]|166|17[0-9]|18[0-9]|19[8|9])\d{8}$/"
        ,'account' => "/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]{6,18}$/u"
        ,'password' => "/^.{6,18}$/"
    ];

    /**
     * @inject
     * @var LoggerInterface
     */
    public $logger;

    /**
     * @inject
     * @var DB
     */
    private $db;    
    
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
     * 用户注册
     *
     * @route POST /
     * @param string $account 账号
     * @param string $reg_type 注册方式 {@v in:account,email,mobile}
     * @param string $pwd 密码
     */
    public function create($account, $reg_type, $pwd){
        $func = new \App\Utils\Func();

        $tablename = 't_users';
        $data = [
            'account' => $account,
            'email' => '',
            'mobile' => '',
            'password' => '',
            'pwd_salt' => strtolower($this->getRandomString(6)),
            'nickname' => $account,
            'avatar' => mt_rand(1, 34), //  用户ID从1000开始, < 100 为系统默认头像， 用户修改头像后， avatar = uid
        ];

        // 密码(长度在6~18之间，只能包含字母、数字和下划线)
        if(!preg_match("/^.{6,20}$/", $pwd)){
            return ['error' => '密码格式不正确,6~20位任意字符'];
        }
        $account_type = $func->CheckAccountType($account);
        switch($reg_type){            
            case 'email': {
                if($account_type !== 'email'){
                    return ['error' => '邮箱格式不正确'];
                }                
                $array = $this->db->select('uid')->from($tablename)->where('email = ?', $account)->get();
                if(count($array) > 0){
                    return ['error' => '邮箱地址已存在'];
                }
                $data['email'] = $account;
                break;
            }
            case 'mobile': {
                if($account_type !== 'mobile'){
                    return ['error' => '手机号码格式不正确'];
                }                
                $array = $this->db->select('uid')->from($tablename)->where('mobile = ?', $account)->get();
                if(count($array) > 0){
                    return ['error' => '手机号码已存在'];
                }
                $data['mobile'] = $account;
                break;
            }
            case 'account': {
                // 帐号是否合法(允许6,18字节，允许中文字母数字下划线)
                if($account_type !== 'account'){
                    return ['error' => '账号格式不正确'];
                }
                if($account_type == 'mobile'){
                    return ['error' => '账号是手机格式，请用手机账号模式注册'];
                }                       
                $array = $this->db->select('uid')->from($tablename)->where('account = ?', $account)->get();
                if(count($array) > 0){
                    return ['error' => '账号已存在'];
                }                 
                break;
            }
        }

        $data['password'] = md5($pwd . $data['pwd_salt']);
        $pdo = $this->db->getConnection();
        $pdo->exec('set names utf8mb4'); // 允许包含emoji表情
        $uid = $this->db->insertInto($tablename)->values($data)->exec()->lastInsertId(); 
        
        $ret['success'] = 1;
        $ret['uid'] = $uid;  
        $ret['nickname'] = $data['nickname'];
        $ret['avatar'] = $data['avatar'];

        $jwt = $func->createToken($uid, $this->jwtKey, $this->host);
        $ret['token'] = $jwt['token'];
        $ret['refreshtoken'] = $jwt['refreshtoken'];

        return $ret;
    }

    /**
     *
     * 用户登录
     *
     * @route POST /login
     * @param string $account 账号
     * @param string $pwd 密码
     */
    public function login($account, $pwd){
        $func = new \App\Utils\Func();
        $account_type = $func->CheckAccountType($account);
        if($account_type === ''){
            return ['error'=> '用户名或密码不正确'];
        }

        $rows = $this->db->select('uid', 'nickname', 'password', 'avatar', 'pwd_salt')
            ->from('t_users')
            ->where("{$account_type} = '{$account}'")
            ->get();

        if(count($rows) > 1){
            $count = count($rows);
            $this->logger->error("find {$count} rows by {$account_type} = {$account} ");
        }

        if(count($rows) !== 1){
            return ['error'=> '用户名或密码不正确'];
        }

        $pwd = md5($pwd . $rows[0]['pwd_salt']);
        if($rows[0]['password'] !== $pwd){
            return ['error'=> '用户名或密码不正确'];
        }
     
        //---------------------------------------------------------------------------------------

        $ret['success'] = 1;
        $ret['uid'] = $rows[0]['uid'];
        $ret['avatar'] = $rows[0]['avatar'];  
        $ret['nickname'] = $rows[0]['nickname']; 

        $jwt = $func->createToken($rows[0]['uid'], $this->jwtKey, $this->host);
        $ret['token'] = $jwt['token'];
        $ret['refreshtoken'] = $jwt['refreshtoken'];

        return $ret; 
    }

    /**
     *
     * 个人信息
     *
     * @route GET /{uid}
     * @param int $clientuid {@bind request.headers.ClientUid}
     */
    public function userInfo($uid, $clientuid = -1){
        $pdo = $this->db->getConnection();
        $pdo->exec('set names utf8mb4');
        
        $sql = "select t1.uid, t1.account, t1.mobile, t1.email, t1.avatar, t1.nickname, t1.`profile`,
                        t2.fans, t2.articles
                from t_users t1 left join 
                    t_users_attr t2 on t1.uid = t2.uid 
                where t1.uid = {$uid}";
        $rows = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
        if($clientuid > 0){
            $sql = "select count(1) as cnt from t_subscribes where uid = {$clientuid} and sub_uid = {$uid}";
            $rows2 = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
            if($rows2[0]['cnt'] > 0){
                $rows[0]['subscribed'] = true;
            }
        }
        $rows[0]['success'] = 1;
        return $rows[0];
    }

    /**
     *
     * 个人信息更新
     *
     * @route PUT /
     * @hook \App\Hooks\BasicAuth
     * @param string $key 字段名 {@v in:mobile,email,avatar,nickname,profile,password}
     * @param string $val 值
     * @param int $clientuid {@bind request.headers.ClientUid}
     * @return string json:{success:1} | {error:''}
     */
    public function update($key, $val, $clientuid){
        $regex = [
        'email' => "/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/",
        'mobile' => "/^(13[0-9]|14[0-9]|15[0-9]|166|17[0-9]|18[0-9]|19[8|9])\d{8}$/",
        'nickname' => "/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]{2,18}$/u",
        'avatar' => "/^[0-9]{1,10}$/",
        ];
        
        if(array_key_exists($key, $regex) && !preg_match($regex[$key], $val)){
            return ['error' => "${key} 格式不正确"];
        }

        $this->db->update('t_users')
                ->set([$key=> $val])
                ->where(['uid'=> $clientuid])
                ->exec();

        return ['success' => 1, "{$key}" => $val];
    }

    /**
     *
     * 修改密码
     *
     * @route PUT /changepassowrd
     * @hook \App\Hooks\BasicAuth
     * @param string $old 旧密码
     * @param string $new 新密码
     * @param int $uid {@bind request.headers.ClientUid}
     * @return string json:{success:1} | {error:''}
     */
    public function changePassowrd($old, $new, $uid){
        $rex = "/^.{6,20}$/";
        if(!preg_match($rex, $old) 
        || !preg_match($rex, $new)){                
            return ['error' => "密码格式不正确,6~20位任意字符"];
        }

        $rows = $this->db->select( 'password', 'pwd_salt')
            ->from('t_users')
            ->where("uid = '{$uid}'")
            ->get();
        
        $pwd_db = $rows[0]['password'];
        $old = md5($old . $rows[0]['pwd_salt']);
        if($old !== $pwd_db){
            return ['error' => "原始密码不正确"];
        }

        $new = md5($new . $rows[0]['pwd_salt']);
        $this->db->update('t_users')
                ->set(['password'=> $new])
                ->where(['uid'=> $uid])
                ->exec();

        return ['success' => 1];
    } 

    private function getRandomString($len, $chars=null){  
        if (is_null($chars)) {
            $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";  
        }  
        mt_srand(10000000*(double)microtime());  
        for ($i = 0, $str = '', $lc = strlen($chars)-1; $i < $len; $i++) {  
            $str .= $chars[mt_rand(0, $lc)];  
        }  
        return $str;  
    }  
} 