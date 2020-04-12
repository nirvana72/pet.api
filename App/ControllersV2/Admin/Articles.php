<?php
namespace App\ControllersV2\Admin;
use PhpBoot\DI\Traits\EnableDIAnnotations;
use App\Hooks\BasicAuth;
use PhpBoot\DB\DB;
use Psr\Log\LoggerInterface;

/**
 * Admin - 文章
 *
 * @path /admin/articles
 */
class Articles
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
   * 文章列表
   *
   * @route GET /
   * @hook \App\Hooks\BasicAuth
   * @param int $page 分页页码
   * @param int $limit 分页分量
   * @param string $title_or_Id 标题或ID
   * @param string $type 类型
   * @param string $status 状态
   * @param string $writetime 发表日期
   * @return string json:{count:1,data:[]}
   */
  public function getList($page=1, $limit=10, $title_or_Id='', $type='', $status='', $writetime=''){
    $ret = ['ret' => -1, 'msg' => 'unknow', 'count' => 0, 'list' => []];

    $where = "";       
    if($title_or_Id !== ''){
        if(preg_match("/^[\d]{1,10}$/", $title_or_Id))
            $where .= " and t1.Id = {$title_or_Id}";
        else
            $where .= " and t1.title like '%{$title_or_Id}%'";
    }        
    if($type !== ''){
        $where .= " and t1.type = '{$type}'";
    }
    if($status !== ''){
        $where .= " and t1.status = {$status}";
    }
    if($writetime !== ''){
        if(substr($writetime, 0, 1) === '~'){
            $tm = substr($writetime, 1);
            $where .= " and t1.writetime <= '{$tm} 23:59:59'";
        }                
        else if(substr($writetime, -1) === '~'){
            $tm = substr($writetime, 0, -1);
            $where .= " and t1.writetime >= '{$tm} 00:00:00'";
        }                
        else{
            $ary = explode('~', $writetime);
            $where .= " and (t1.writetime between '{$ary[0]} 00:00:00' and '{$ary[1]} 23:59:59')";
        }
    }        

    $pdo = $this->db->getConnection();
    $pdo->exec('set names utf8mb4');
    $sql = "select count(1) as cnt from t_articles t1 where true {$where}";
    
    $result = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
    $ret['count'] = intVal($result[0]['cnt']);

    $start = ($page-1) * $limit;
    $sql = "
    select t1.Id, t1.title, t1.type, t1.authorId, t1.`status`, t1.writetime,
            t2.nickname as authorname
    from t_articles t1 left join t_users t2 on t1.authorId = t2.uid 
    where true {$where}
    order by t1.writetime desc
    limit {$start},{$limit}";
    $result = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);

    $ret['list'] = $result;
    $ret['ret'] = 1;
    $ret['msg'] = 'success';
    return $ret;
  }

  /**
   *
   * 文章
   *
   * @route GET /{Id}
   * @hook \App\Hooks\BasicAuth
   */
  public function getItem($Id){
    $pdo = $this->db->getConnection();
    $pdo->exec('set names utf8mb4');
    $sql = "select Id,title,type,authorId,status,writetime from t_articles where Id = {$Id}";
    $articles = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
    if($articles[0]['type'] === 'rich'){
        $sql = "select content from t_articles_rich where Id = {$Id}";
        $rich = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
        $articles[0]['content'] = $rich[0]['content'];
    }

    $sql = "select Id, type, fname, duration from t_articles_oss where AId = {$Id}";
    $medias = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
    $articles[0]['images'] = [];
    $articles[0]['videos'] = [];
    foreach($medias as $media){
        if($media['type'] === 'image'){
            $articles[0]['images'][] = $media['fname'];
        }
        if($media['type'] === 'video'){
            $item['fname'] = $media['fname'];
            $item['duration'] = $media['duration'];
            $articles[0]['videos'][] = $item;
        }
    }
    $res['ret'] = 1;
    $res['msg'] = 1;
    $res['article'] = $articles[0];
    return $res;
  }

  /**
   *
   * 文章发布 状态 2
   *
   * @route PUT /{Id}/publish
   * @hook \App\Hooks\BasicAuth
   * @param string $title 发布富文本时更新修正后的标题
   * @param string $content 更新修正后的正文
   */
  public function publish($Id, $title = '', $content = ''){
    $pdo = $this->db->getConnection();
    $pdo->exec('set names utf8mb4');
    // 长贴文章 发布时更新修正后的正文， 如未作修正，也默认更新一下
    if($content !== ''){            
        //生成摘要
        $abstract = htmlspecialchars_decode($content);//把一些预定义的 HTML 实体转换为字符
        $abstract = str_replace(" ","",$abstract);//将空格替换成空
        $abstract = str_replace("&nbsp;","",$abstract);//将空格替换成空
        $abstract = strip_tags($abstract);//函数剥去字符串中的 HTML、XML 以及 PHP 的标签,获取纯文本内容
        if(strlen($abstract) > 100){
            $abstract = mb_substr($abstract, 0, 100) . '...';//返回字符串中的前100字符串长度的字符
        }

        $this->db->update('t_articles_rich')->set(['abstract'=> $abstract, 'content'=> $content])->where(['Id'=> $Id])->exec();
    }

    $result = $pdo->exec("update t_users_attr set articles = articles + 1 where uid = (select authorId from t_articles where Id = {$Id})");
    if($result !== 1){
        $pdo->exec("insert into t_users_attr (uid, articles) select authorId as uid, 1 as articles from t_articles where Id = {$Id}");
    }
    
    $ret = $this->db->update('t_articles')->set(['title'=> $title, 'status'=> 2])->where(['Id'=> $Id])->exec();
    if($ret->rows === 1)
        return ['ret' => 1, 'msg' => 'success', 'status'=> 2];
    else
        return ['ret' => -1, 'msg' => '影响行数 0'];
  }

  /**
   *
   * 文章拒绝发布 状态 10
   *
   * @route PUT /{Id}/reject
   * @hook \App\Hooks\BasicAuth
   * @param string $reason 理由
   * @return string json:{success:1, status:10} | {error:''}
   */
  public function reject($Id, $reason){
    $ret = $this->db->select(['title','authorId'])->from('t_articles')->where("Id = {$Id}")->get();
    $title = $ret[0]['title'];
    $authorId = $ret[0]['authorId'];

    $reasonContent = "您发布的文章 [ {$title} ], 由于 {$reason} 原因，拒绝发表";
    
    // 本来想弄个消息表，有空再做消息模块， 现在改用IM通知
    // $this->db
    //     ->insertInto('t_message')
    //     ->values([
    //         'content'=>$reasonContent,
    //         'getUid'=> $authorId,
    //         'postUid'=> 0
    //     ])->exec();

    $ret = $this->db->update('t_articles')->set(['status'=> 10])->where(['Id'=> $Id])->exec();
    
    // 发送系统消息
    $rcim = new \App\ControllersV2\Admin\RongCloudApi();
    $rcim->sendUserMessage($authorId, $reasonContent);
    
    if($ret->rows === 1)
        return ['ret' => 1, 'msg' => 'success','status'=> 10];
    else
        return ['ret' => -1, 'msg' => '影响行数 0'];
  }

  /**
   *
   * 彻底删除文章
   *
   * @route delete /{Id}/remove
   * @hook \App\Hooks\BasicAuth
   */
  public function remove($Id){ 
    $result = $this->db->select(['writetime'])->from('t_articles')->where("Id = {$Id}")->get();
    if (count($result) != 0) {
      $ret['ret'] = -1;
      $ret['msg'] = '文章不存在';
      return $ret;
    }
    
    $accessKeyId = "LTAIHMSR3i94XGvl";
    $accessKeySecret = "kJZ7VOwgUpxlCR88Ls27rjOtm0gNn8";
    $endpoint = "https://oss-cn-shanghai.aliyuncs.com";
    $bucket= "nij20190123";
    $ossfiles = $this->db->select(['fname'])->from('t_articles_oss')->where("AId = {$Id}")->get(); 
    $writetime = $result[0]['writetime'];  
    $writetime = str_replace('-', '', $writetime);
    $writetime = substr($writetime, 0, 6);
    $objects = [];
    foreach($ossfiles as $row) {
      $objects[] = "articles/{$writetime}/${Id}/{$row['fname']}";
    }

    if (count($objects) > 0) {
      try{
        $ossClient = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint);
        $ossClient->deleteObjects($bucket, $objects);
      } catch(\Exception $e) {
          // printf(__FUNCTION__ . ": FAILED\n");
          // printf($e->getMessage() . "\n");
          // return;
          $ret['ret'] = -1;
          $ret['msg'] = '删除OSS资源出错:' . $e->getMessage();
          return $ret;
      }
    }    

    $this->db->deleteFrom('t_articles')->where(['Id'=>$Id])->exec();        
    $this->db->deleteFrom('t_articles_oss')->where(['AId'=>$Id])->exec();   // 删除资源引用
    $this->db->deleteFrom('t_articles_rich')->where(['Id'=>$Id])->exec();   // 如是富文本，删除正文
    $this->db->deleteFrom('t_articles_likes')->where(['AId'=>$Id])->exec(); // 删除收藏
    $this->db->deleteFrom('t_comments')->where(['target_id'=>$Id])->exec(); // 删除评论

    $ret['ret'] = 1;
    $ret['msg'] = 'success';
    return $ret;
  }
} 