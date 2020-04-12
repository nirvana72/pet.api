<?php
namespace App\ControllersV2\App;
use PhpBoot\DI\Traits\EnableDIAnnotations;
use PhpBoot\DB\DB;
use App\Hooks\BasicAuth;

/**
 * APP - 评论
 *
 * @path /app/comments
 */
class Comments
{
    use EnableDIAnnotations;

    /**
     * @inject
     * @var DB
     */
    private $db;    

    /**
     *
     * 发布评论
     *
     * @route POST /
     * @hook \App\Hooks\BasicAuth
     * @param int $target_id 评论主题的id,article_id、course_id等
     * @param int $parent_id 评论组id，回复主评论时可以0
     * @param int $reply_id 回复的id，无回复对象时为0
     * @param string $content 评论内容
     * @param int $uid {@bind request.headers.ClientUid}
     */
    public function create($target_id, $parent_id, $reply_id, $content, $uid){
        $pdo = $this->db->getConnection();
        $pdo->exec('set names utf8mb4');
  
        // 回复主评论时
        if($parent_id > 0 && $parent_id == $reply_id) {
          $reply_id = 0;
        }
  
        $data = [
            'target_id' => $target_id,
            'parent_id' => $parent_id,
            'reply_id' => $reply_id,
            'uid' => $uid,
            'content' => $content,
            'writetime' => date('Y-m-d H:i:s'),
            'status' => 1
        ];
  
        $Id = $this->db->insertInto('t_comments')->values($data)->exec()->lastInsertId(); 
  
        $sql = "update t_articles set comments = comments + 1 where Id = {$target_id}";
        $pdo->exec($sql);

        return [
            'ret' => 1, 
            'msg' => 'success', 
            'Id' => $Id, 
            'writetime' => $data['writetime']
        ];
      }

    /**
     *
     * 获取评论
     *
     * @route GET /
     * @param int $target_id 评论主题的id,article_id、course_id等
     * @param int $page 加载记录
     */
    public function getlist($target_id, $page = 1){

        $pdo = $this->db->getConnection();
        $pdo->exec('set names utf8mb4');

        $limit = 10;
        $start = ($page-1) * $limit;
        $sql = "
        select t1.Id, t1.target_id, t1.parent_id, t1.reply_id, t1.uid, t1.content, t1.writetime, t1.`status`, t1.voteup, t1.votedown
            ,t2.nickname as uname, t2.avatar
        from t_comments t1 left outer join 
            t_users t2 on t1.uid = t2.uid
        where target_id = {$target_id} and parent_id = 0 
        order by Id desc 
        limit {$start},{$limit};";

        $result1 = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
        $result1_count = count($result1);
        
        if ($result1_count > 0) {
        $parentIds = "";
        foreach($result1 as $row) {
            $parentIds .= $row['Id'] . ',';
        }
        $parentIds = substr($parentIds, 0, -1);

        $sql = "
        select tb1.*, tb2.nickname as uname, tb2.avatar, tb3.nickname as reply_name from (
            select t1.Id, t1.target_id, t1.parent_id, t1.reply_id, t1.uid, t1.content, t1.writetime, t1.`status`, t1.voteup, t1.votedown
                ,IFNULL(t2.uid, 0) as reply_uid
            from t_comments t1 left outer join  
                t_comments t2 on t2.target_id = {$target_id} and t1.reply_id = t2.Id
            where t1.target_id = {$target_id}
            and t1.parent_id in ({$parentIds}) 
            and 10 > (select count(*) from t_comments where target_id = {$target_id} and parent_id = t1.parent_id and writetime < t1.writetime ) 
        ) tb1 left outer join 
            t_users tb2 on tb1.uid = tb2.uid  left outer JOIN
            t_users tb3 on tb1.reply_uid = tb3.uid
        order by tb1.parent_id desc, tb1.writetime";
        
        $result2 = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
        $result2_count = count($result2);
        $index1 = 0;
        $index2 = 0;
        while($index1 < $result1_count && $index2 < $result2_count) {
            if ($result2[$index2]['parent_id'] == $result1[$index1]['Id']){
            $result1[$index1]['comments'][] = $result2[$index2];
            $index2 ++;
            } else {
            $index1 ++;
            }
        }
        }
        
        $ret['ret'] = 1;
        $ret['msg'] = 'success';
        $ret['comments'] = $result1;
        return $ret;
    }

    /**
     *
     * 加载子评论
     *
     * @route GET /sub
     * @param int $target_id 评论主题的id,article_id、course_id等
     * @param int $parent_id 回复主评论的Id
     * @param int $last_id 起始Id
     */
    public function getSublist($target_id, $parent_id, $last_id){

        $pdo = $this->db->getConnection();
        $pdo->exec('set names utf8mb4');

        $sql = "
        select tb1.*, tb2.nickname as uname, tb2.avatar, tb3.nickname as reply_name from (
            select t1.Id, t1.target_id, t1.parent_id, t1.reply_id, t1.uid, t1.content, t1.writetime, t1.`status`, t1.voteup, t1.votedown
                ,IFNULL(t2.uid, 0) as reply_uid
            from t_comments t1 left outer join  
                t_comments t2 on t2.target_id = {$target_id} and t1.reply_id = t2.Id
            where t1.target_id = {$target_id}
            and t1.parent_id = {$parent_id}
            and t1.Id > {$last_id} 
            limit 10
        ) tb1 left outer join 
            t_users tb2 on tb1.uid = tb2.uid  left outer JOIN
            t_users tb3 on tb1.reply_uid = tb3.uid
        order by tb1.parent_id desc, tb1.writetime";
        
        $result = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);

        $ret['ret'] = 1;
        $ret['msg'] = 'success';
        $ret['comments'] = $result;
        return $ret;
    }

    /**
     *
     * 评论点赞
     *
     * @route POST /vote
     * @param int $commentId 评论id
     * @param string $cmd 点赞方式 {@v in:voteup,votedown}
     */
    public function vote($commentId, $cmd){
        $sql = "update t_comments set {$cmd} = {$cmd} + 1 where Id = {$commentId}";
        $pdo = $this->db->getConnection();
        $pdo->exec($sql);
        return [
            'ret' => 1, 
            'msg' => 'success'
        ];
    }
} 