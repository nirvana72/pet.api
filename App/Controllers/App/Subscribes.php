<?php
namespace App\Controllers\App;
use PhpBoot\DI\Traits\EnableDIAnnotations;
use PhpBoot\DB\DB;
use Psr\Log\LoggerInterface;
use App\Hooks\BasicAuth;

/**
 * APP - 关注模块
 *
 * @path /app/subscribes
 */
class Subscribes
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
     * 设置关注
     *
     * @route PUT /{uid}
     * @hook \App\Hooks\BasicAuth
     * @param int $clientuid {@bind request.headers.ClientUid}
     */
    public function setSubscribe($uid, $clientuid){
      $pdo = $this->db->getConnection();
      $sql = "insert into t_subscribes (uid, sub_uid) values ({$clientuid}, {$uid})";
      $result = $pdo->exec($sql);

      $sql = "update t_users_attr set fans = fans + 1 where uid = {$uid}";
      $result = $pdo->exec($sql);
      if($result !== 1){
          $sql = "insert into t_users_attr (uid, fans) values ({$uid}, 1)";
          $result = $pdo->exec($sql);
      }
      return ['success' => 1];
    }

    /**
     *
     * 取消关注
     *
     * @route DELETE /{uid}
     * @hook \App\Hooks\BasicAuth
     * @param int $clientuid {@bind request.headers.ClientUid}
     */
    public function deleteSubscribe($uid, $clientuid){
        $pdo = $this->db->getConnection();
        $sql = "delete from t_subscribes where uid = {$clientuid} and sub_uid = {$uid}";
        $pdo->exec($sql);
        
        try{
            $sql = "update t_users_attr set fans = fans - 1 where uid = {$uid}";
            $result = $pdo->exec($sql);
        }catch (\Exception $e){
            
        }
        
        return ['success' => 1];
    }

    /**
     *
     * 关注列表
     *
     * @route GET /{uid}
     * @hook \App\Hooks\BasicAuth
     * @param int $page 分页页码
     * @param int $limit 分页分量
     */
    public function getSubscribes($uid, $page, $limit){
        $ret = ['count'=> 0, 'list'=> []];

        $start = ($page-1) * $limit;
        $pdo = $this->db->getConnection();
        $pdo->exec('set names utf8mb4');
        
        if($page === 1){
            $sql = "select count(1) as cnt from t_subscribes where uid = {$uid}";
            $rst = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
            $ret['count'] = intval($rst[0]['cnt']);
        }

        $sql = "select t1.sub_uid as uid, 
        t2.nickname, t2.avatar, t2.`profile`,
        t3.fans, t3.articles
        from (
            select sub_uid from t_subscribes where uid = {$uid} order by writetime desc limit {$start},{$limit}
        ) t1 left outer join 
          t_users t2 on t1.sub_uid = t2.uid left outer join 
          t_users_attr t3 on t1.sub_uid = t3.uid";
        $ret['list'] =  $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
        
        return $ret;
    }

    /**
     *
     * 粉丝列表
     *
     * @route GET /{uid}/fans
     * @hook \App\Hooks\BasicAuth
     * @param int $page 分页页码
     * @param int $limit 分页分量
     */
    public function getFans($uid, $page, $limit){
        $ret = ['count'=> 0, 'list'=> []];

        $start = ($page-1) * $limit;
        $pdo = $this->db->getConnection();
        $pdo->exec('set names utf8mb4');

        if($page === 1){
            $sql = "select count(1) as cnt from t_subscribes where sub_uid = {$uid}";
            $rst = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
            $ret['count'] = intval($rst[0]['cnt']);
        }

        $sql = "select t1.uid, 
        t2.nickname, t2.avatar, t2.`profile`,
        t3.fans, t3.articles
        from (
            select uid from t_subscribes where sub_uid = {$uid} order by writetime desc limit {$start},{$limit}
        ) t1 left outer join 
          t_users t2 on t1.uid = t2.uid left outer join 
          t_users_attr t3 on t1.uid = t3.uid";
        $ret['list'] = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
        
        return $ret;
    }
} 