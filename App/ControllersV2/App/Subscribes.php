<?php
namespace App\ControllersV2\App;
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
        if ($clientuid < 0) {
            return ['ret' => -1, 'msg' => '用户未登录'];
        }
        $pdo = $this->db->getConnection();
        // 数据库双主键，已规避重复关注情况
        $sql = "insert into t_subscribes (uid, sub_uid) values ({$clientuid}, {$uid})";
        $result = $pdo->exec($sql);
  
        if($result === 1){
            $sql = "update t_users_attr set fans = fans + 1 where uid = {$uid}";
            $result = $pdo->exec($sql);
            if($result !== 1){
                $sql = "insert into t_users_attr (uid, fans) values ({$uid}, 1)";
                $result = $pdo->exec($sql);
            }
        }        
        return ['ret' => 1, 'msg' => 'success'];
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
        if ($clientuid < 0) {
            return ['ret' => -1, 'msg' => '用户未登录'];
        }
        $pdo = $this->db->getConnection();
        $sql = "delete from t_subscribes where uid = {$clientuid} and sub_uid = {$uid}";
        $pdo->exec($sql);
        
        try{
            $sql = "update t_users_attr set fans = fans - 1 where uid = {$uid}";
            $result = $pdo->exec($sql);
        }catch (\Exception $e){
            return ['ret' => -1, 'msg' => '取消关注失败'];
        }
        
        return ['ret' => 1, 'msg' => 'success'];
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
        $start = ($page-1) * $limit;
        $pdo = $this->db->getConnection();
        $pdo->exec('set names utf8mb4');
        
        $count = 0;
        if($page === 1){
            $sql = "select count(1) as cnt from t_subscribes where uid = {$uid}";
            $rst = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
            $count = intval($rst[0]['cnt']);
        }

        $sql = "select t1.sub_uid as uid, 
        t2.nickname, t2.avatar, t2.`profile`,
        t3.fans, t3.articles
        from (
            select sub_uid from t_subscribes where uid = {$uid} order by writetime desc limit {$start},{$limit}
        ) t1 left outer join 
          t_users t2 on t1.sub_uid = t2.uid left outer join 
          t_users_attr t3 on t1.sub_uid = t3.uid";

        $result['list'] =  $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);        
        $result['ret'] = 1;
        $result['msg'] = 'success';
        $result['count'] = $count;
        return $result;
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
        $start = ($page-1) * $limit;
        $pdo = $this->db->getConnection();
        $pdo->exec('set names utf8mb4');

        $count = 0;
        if($page === 1){
            $sql = "select count(1) as cnt from t_subscribes where sub_uid = {$uid}";
            $rst = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
            $count = intval($rst[0]['cnt']);
        }

        $sql = "select t1.uid, 
        t2.nickname, t2.avatar, t2.`profile`,
        t3.fans, t3.articles
        from (
            select uid from t_subscribes where sub_uid = {$uid} order by writetime desc limit {$start},{$limit}
        ) t1 left outer join 
          t_users t2 on t1.uid = t2.uid left outer join 
          t_users_attr t3 on t1.uid = t3.uid";
        
        $result['list'] =  $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);        
        $result['ret'] = 1;
        $result['msg'] = 'success';
        $result['count'] = $count;
        return $result;
    }
} 