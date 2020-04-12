<?php
namespace App\Controllers\Admin;
use PhpBoot\DI\Traits\EnableDIAnnotations;
use App\Hooks\BasicAuth;
use PhpBoot\DB\DB;
use Psr\Log\LoggerInterface;

/**
 * Admin - 管理员
 *
 * @path /admin/admins
 */
class Admins
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
     * 管理员登录
     *
     * @route POST /login
     * @param string $account 账号
     * @param string $pwd 密码
     */
    public function login($account, $pwd){
      $func = new \App\Utils\Func();
      $account_type = $func->CheckAccountType($account);
      if($account_type === ''){
          return ['error' => '用户名或密码不正确'];
      }

      $rows = $this->db->select('uid', 'nickname', 'password', 'pwd_salt')
          ->from('t_users')
          ->where("{$account_type} = '{$account}'")
          ->get();

      if(count($rows) > 1){
          $count = count($rows);
          $this->logger->error("find {$count} rows by {$account_type} = {$account} ");
      }

      if(count($rows) !== 1){
        return ['error' => '用户名或密码不正确'];
      }
      
      $pwd = md5($pwd . $rows[0]['pwd_salt']);
      if($rows[0]['password'] !== $pwd){
        return ['error' => '用户名或密码不正确'];
      }
      //---------------------------------------------------------------------------------------
      $uid = $rows[0]['uid'];
      
      $rows2 = $this->db->select('uid','realname','role','lasttime')
          ->from('t_admin')
          ->where(['uid'=> $uid, 'isdel'=> 0])
          ->get();
      
      if(count($rows2) !== 1){
        return ['error' => '不是管理员'];
      }
     
      $this->db->update('t_admin')
        ->set(['lasttime'=> date('Y-m-d H:i:s')])
        ->where(['uid'=> $uid])
        ->exec();
     
      //---------------------------------------------------------------------------------------
      $jwt = $func->createToken($uid, "{$this->jwtKey}admin", $this->host);
      $rows2[0]['token'] = $jwt['token'];
      $rows2[0]['refreshtoken'] = $jwt['refreshtoken'];

      return ['success'=>1, 'user'=>$rows2[0]]; 
    }

    /**
     *
     * 获取管理员列表
     *
     * @route GET /
     * @hook \App\Hooks\BasicAuth
     */
    public function getUser(){
      return $this->db->select('uid','realname','role','lasttime')
          ->from('t_admin')
          ->where(['isdel'=> 0])
          ->get();
    }

     /**
     *
     * 添加管理员
     *
     * @route POST /
     * @hook \App\Hooks\BasicAuth
     * @param int $uid 用户ID
     * @param string $realname 真实姓名
     * @param string $role 角色
     */
    public function postUser($uid, $realname, $role){
      $rows = $this->db->select('uid')
          ->from('t_users')
          ->where("uid = {$uid}")
          ->get();

      if(count($rows) !== 1){
        return ['error'=> '用户Id不存在'];
      }

      $this->db->insertInto('t_admin')
        ->values([
          'uid'=>$uid,
          'realname'=>$realname,
          'role'=>$role,
        ])
        ->exec();

      return ['success'=> 1];
    }

    /**
     *
     * 修改管理员
     *
     * @route PUT /
     * @hook \App\Hooks\BasicAuth
     * @param int $uid 用户ID
     * @param string $realname 真实姓名
     * @param string $role 角色
     */
    public function putUser($uid, $realname, $role){
      $this->db->update('t_admin')
        ->set(['realname'=> $realname, 'role'=> $role])
        ->where(['uid'=> $uid])
        ->exec();

      return ['success'=> 1];
    }

     /**
     *
     * 删除管理员
     *
     * @route DELETE /
     * @hook \App\Hooks\BasicAuth
     * @param int $uid 用户ID
     * @param int $myid {@bind request.headers.ClientUid}
     */
    public function deleteUser($uid, $myid){
      if($myid === $uid){
        return ['error'=> '不能删除自己'];
      }

      $rows = $this->db->select('uid','realname','role','lasttime')
          ->from('t_admin')
          ->where(['uid'=> $uid])
          ->get();

      if($rows[0]['role'] === 'admin'){
        return ['error'=> '不能删除admin账号'];
      }
      
      $this->db->deleteFrom('t_admin')
        ->where(['uid'=>$uid])
        ->exec();

      return ['success'=> 1];
    }
} 