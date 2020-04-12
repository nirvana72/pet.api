<?php
namespace App\ControllersV2\App;
require_once __DIR__ . "/../../../RongCloud/RongCloud.php";
use PhpBoot\DI\Traits\EnableDIAnnotations;
use PhpBoot\DB\DB;
/**
 * APP - RongCloudApi
 *
 * @path /app/RongCloudApi
 */
class RongCloudApi
{
    use EnableDIAnnotations; //启用通过@inject标记注入依赖

    /**
     * @inject
     * @var DB
     */
    private $db;

    private $APPKEY = 'mgb7ka1nmdugg';
    private $APPSECRET = '6yCGuI2lQF';

    /**
     *
     * register
     *
     * @route GET /register
     * @param int $uid uid
     */
    public function register($uid){  
        $pdo = $this->db->getConnection();

        // 先查询数据库中是否有已登录token，融云token永不过期
        $sql = "select token from t_rongcloud_users where uid = {$uid}";
        $rows = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
        if (count($rows) === 1) { 
            return [
                'ret' => 1,
                'msg' => 'success',
                'token' => $rows[0]['token']
            ];
        } 

        $sql = "select uid, nickname, avatar from t_users where uid = {$uid}";
        $rows = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
        if (count($rows) === 1) {
            $func = new \App\Utils\Func();
            $portrait = $func->convertAvatar($rows[0]['avatar']);  
           
            $user = [
                'id'=> $uid, 
                'name'=> $rows[0]['nickname'],//用户名称
                'portrait'=> $portrait //用户头像
            ];

            $RongSDK = new \RongCloud\RongCloud($this->APPKEY, $this->APPSECRET);
            $result = $RongSDK->getUser()->register($user);           
            if ($result['code'] === 200) {
                $pdo->exec("insert into t_rongcloud_users (uid, token) values ('{$uid}', '{$result['token']}')");
                return [
                    'ret' => 1,
                    'msg' => 'success',
                    'token' => $result['token']
                ];
            } else {
                return ['ret' => -1,'msg' => 'RongCloud getToken fail'];
            }            
        }
        else {
            return ['ret' => -1,'msg' => '用户不存在'];
        }
    }

    /**
     *
     * update
     *
     * @route GET /update
     * @param int $uid uid
     */
    public function update($uid){  
        $pdo = $this->db->getConnection();

        $sql = "select uid, nickname, avatar from t_users where uid = {$uid}";
        $rows = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
        if (count($rows) === 1) {
            $func = new \App\Utils\Func();
            $portrait = $func->convertAvatar($rows[0]['avatar']);  
           
            $user = [
                'id'=> $uid, 
                'name'=> $rows[0]['nickname'],//用户名称
                'portrait'=> $portrait //用户头像
            ];

            $RongSDK = new \RongCloud\RongCloud($this->APPKEY, $this->APPSECRET);
            $result = $RongSDK->getUser()->update($user);
            if ($result['code'] === 200) {
                return ['ret' => 1,'msg' => 'success'];
            }
            else {
                return ['ret' => -1,'msg' => 'RongCloud refresh fail'];
            }
        }
        else {
            return ['ret' => -1,'msg' => '用户不存在'];
        }
    }
} 