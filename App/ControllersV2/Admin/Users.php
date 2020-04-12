<?php
namespace App\ControllersV2\Admin;
use PhpBoot\DI\Traits\EnableDIAnnotations;
use App\Hooks\BasicAuth;
use PhpBoot\DB\DB;
use Psr\Log\LoggerInterface;

/**
 * Admin - 用户管理
 *
 * @path /admin/users
 */
class Users
{
  use EnableDIAnnotations; 

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
   *
   * 用户列表
   *
   * @route GET /
   * @hook \App\Hooks\BasicAuth
   * @param int $page 分页页码
   * @param int $limit 分页分量
   * @param string $uid uid
   * @param string $account 账号
   */
  public function getList($page=1, $limit=10, $uid='', $account=''){
    $ret = ['ret'=> 1, 'msg'=> 'success', 'count' => 0, 'list' => []];

    $where = "";        
    if($uid !== ''){
        $where .= " and t1.uid = '{$uid}'";
    }
    if($account !== ''){
        $where .= " and t1.account like '%{$account}%'";
    }
    
    $pdo = $this->db->getConnection();
    $pdo->exec('set names utf8mb4');
    
    $sql = "select count(1) as cnt from t_users t1 where true {$where}";
    
    $result = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
    $ret['count'] = intVal($result[0]['cnt']);

    $start = ($page-1) * $limit;
    $sql = "
select t1.uid, t1.account, t1.email, t1.mobile, t1.nickname, t1.avatar, t1.writetime, t1.lasttime
from t_users t1
where true {$where}
limit {$start},{$limit}";
    $result = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
    $ret['list'] = $result;

    return $ret;
  }

  /**
   *
   * 用户属性
   *
   * @route GET /{uid}/setting
   * @hook \App\Hooks\BasicAuth
   */
  public function getSetting($uid){
    $pdo = $this->db->getConnection();
    $sql = "select `key`,`val` from t_users_setting where uid={$uid}";
    $result = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);

    $res['ret'] = 1;
    $res['msg'] = 'success';
    $res['settings'] = $result;
    return $res;
  }

  /**
   *
   * 用户属性设置
   *
   * @route PUT /{uid}/setting
   * @hook \App\Hooks\BasicAuth
   * @param string $key 键:article.publish|module.action
   * @param string $val 值
   */
  public function Setting($key, $val, $uid){           
    $sql = "";
    switch($key){
      case 'article.publish':{
        if($val === '0')
          $sql = "replace into t_users_setting(`uid`,`key`,`val`) values ('{$uid}','article.publish','0')";
        if($val === '1')
          $sql = "delete from t_users_setting where uid={$uid} and `key`='article.publish'";
        break;
      }
      case 'article.reply':{
        if($val === '0')
          $sql = "replace into t_users_setting(`uid`,`key`,`val`) values ('{$uid}','article.reply','0')";
        if($val === '1')
          $sql = "delete from t_users_setting where uid={$uid} and `key`='article.reply'";              
        break;
      }
      case 'user.password':{
        $pwd_salt = strtolower($this->getRandomString(6));
        $password = md5('88888888' . $pwd_salt);
        $sql = "update t_users set `password` = '{$password}', `pwd_salt` = '{$pwd_salt}' where uid = {$uid}";  
        break;
      }
    }

    if($sql === ''){
        return ['ret' => -1,'msg' => "${key} 格式不正确"];
    }
    $pdo = $this->db->getConnection();
    $pdo->exec($sql);
    
    return ['ret' => 1,'msg'=>'success'];
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