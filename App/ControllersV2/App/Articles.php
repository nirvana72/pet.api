<?php
namespace App\ControllersV2\App;
use PhpBoot\DI\Traits\EnableDIAnnotations;
use PhpBoot\DB\DB;
use Psr\Log\LoggerInterface;
use App\Hooks\BasicAuth;

/**
 * APP - 文章
 *
 * @path /app/articles
 */
class Articles
{
    use EnableDIAnnotations; //启用通过@inject标记注入依赖

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
     * 获取文章列表
     *
     * @route GET /
     * @param string $time 上次拉取时间
     * @param int $limit 加载数量
     * @param string $search 查询条件
     * @param int $uid {@bind request.headers.ClientUid}
     * @return string json:[{id,title,type....medias:[{type,name}...]},...]
     */
    public function getList($time = '', $limit = 10, $search = '', $uid = -1){
        $pdo = $this->db->getConnection();
        $pdo->exec('set names utf8mb4');

        $where = "where t1.`status` in (1,2) ";
        if(preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',$time)){
            $where .= " and t1.writetime < '{$time}'";
        }
      	if($search != '') {
            $where .= " and t1.title like '%{$search}%'";
        }
        $sql = "
        select  t1.Id, t1.title, t1.type, t1.authorId, t1.writetime, t1.status, t1.likes, t1.lauds, t1.comments, t1.postAddr,
                t2.nickname as authorname, t2.avatar,
                t3.abstract
        from t_articles t1 left outer join
            t_users t2 on t1.authorId = t2.uid left outer JOIN
            t_articles_rich t3 on t1.Id = t3.Id
        {$where} 
        order by t1.writetime desc 
        limit {$limit}";

        $articles = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
        $len = count($articles);
        
        if($len > 0){
            $Ids = '';
            $authors = '';
            for($i = 1; $i <= $len; $i++){
                $Ids = $Ids . $articles[$i-1]['Id'] . ($i === $len? '' : ',');
                $authors = $authors . $articles[$i-1]['authorId'] . ($i === $len? '' : ',');
            }

            // 收藏
            if($uid > 0){               
                $sql = "select AId from t_articles_likes where uid = {$uid} and AId in ({$Ids})";
                $likes = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
                foreach($likes as $like){
                    for($i = 0; $i < $len; $i++){            
                        if($like['AId'] === $articles[$i]['Id']){
                            $articles[$i]['liked'] = true;
                            break;
                        }
                    }
                }
            }

            // 关注
            if($uid > 0){               
                $sql = "select sub_uid from t_subscribes where uid = {$uid} and sub_uid in ({$authors})";
                $subs = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
                foreach($subs as $sub){
                    for($i = 0; $i < $len; $i++){            
                        if($sub['sub_uid'] === $articles[$i]['authorId']){
                            $articles[$i]['subscribe'] = true;
                        }
                    }
                }
            }

            // 资源
            $sql = "select AId, type, fname, duration from t_articles_oss where AId in ({$Ids})";
            $medias = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
            for($i = 0; $i < $len; $i++){
                $articles[$i]['videos']  = array(); 
                $articles[$i]['images']  = array();                                
                foreach($medias as $media){
                    if($media['AId'] === $articles[$i]['Id']){
                        if($media['type'] === 'image'){
                            $articles[$i]['images'][] = $media['fname'];
                        }
                        if($media['type'] === 'video'){
                            $item['fname'] = $media['fname'];
                            $item['duration'] = $media['duration'];
                            $articles[$i]['videos'][] = $item;
                        }
                    }
                }
            }
        }

        $result['ret'] = 1;
        $result['msg'] = 'success';
        $result['articles'] = $articles;
        return $result;
    }

    /**
     *
     * 待审核的文章列表
     *
     * @route GET /reviewlist
     * @hook \App\Hooks\BasicAuth
     * @param int $uid {@bind request.headers.ClientUid}
     */
    public function getReviewList($uid = -1){
        $pdo = $this->db->getConnection();
        $pdo->exec('set names utf8mb4');

        $sql = "select uid from t_admin where uid = {$uid} and role = 'admin'";
        $result = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
        if (count($result) !== 1) {
            return ['ret' => -1, 'msg' => '不是审核人员'];
        }

        $sql = "
        select t1.Id, t1.title, t1.type, t1.authorId, t1.`status`, t1.writetime,
                t2.nickname as authorname
        from t_articles t1 left join t_users t2 on t1.authorId = t2.uid 
        where t1.status = 1
        order by t1.writetime desc";
        $result = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);

        $ret['ret'] = 1;
        $ret['msg'] = 'success';
        $ret['list'] = $result;

        return $ret;
    }

       /**
     *
     * APP中审核文章
     *
     * @route PUT /{aid}/review
     * @hook \App\Hooks\BasicAuth
     * @param string $cmd 操作 {@v in:publish,reject}
     * @param string $reason 理由
     * @param int $uid {@bind request.headers.ClientUid}
     */
    public function ClientReview($aid, $cmd, $reason = '', $uid = -1){
        // $ret['ret'] = 1;
        // $ret['msg'] = 'test';

        $pdo = $this->db->getConnection();
        $pdo->exec('set names utf8mb4');

        $sql = "select uid from t_admin where uid = {$uid} and role = 'admin'";
        $result = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
        if (count($result) !== 1) {
            return ['ret' => -1, 'msg' => '不是审核人员'];
        }

        if ($cmd == 'publish') {
            // 更改状态
            $result = $this->db->update('t_articles')->set(['status'=> 2])->where(['Id'=> $aid])->exec();
            $success = $result->rows === 1;
            $ret['ret'] = $success ? 1 : -1;
            $ret['msg'] = $success ? 'success' : "影响行数 {$result->rows}";

            if ($success) {
                // 更新用户发布的文章数
                $result = $this->db->select(['authorId'])->from('t_articles')->where("Id = {$aid}")->get();
                $authorId = $result[0]['authorId'];              
                $result = $pdo->exec("update t_users_attr set articles = (select count(1) from t_articles where authorId = {$authorId} and `status` = 2) where uid = {$authorId}");
                if($result !== 1){
                    $pdo->exec("insert into t_users_attr (uid, articles) values ({$authorId}, 1)");
                }
            }
        }

        if ($cmd == 'reject') {
            $result = $this->db->select(['title','authorId'])->from('t_articles')->where("Id = {$aid}")->get();
            $title = $result[0]['title'];
            $authorId = $result[0]['authorId'];    
            $reasonContent = "您发布的文章 [ {$title} ], 由于 {$reason} 原因，拒绝发表";    
            // 更改状态
            $result = $this->db->update('t_articles')->set(['status'=> 10])->where(['Id'=> $aid])->exec();
            $success = $result->rows === 1;
            $ret['ret'] = $success ? 1 : -1;
            $ret['msg'] = $success ? 'success' : "影响行数 {$result->rows}";
            // 发送系统消息
            $rcim = new \App\Controllers\Admin\RongCloudApi();
            $rcim->sendUserMessage($authorId, $reasonContent);
        }
        // 记录审核人
        $pdo->exec("update t_articles set reviewer = {$uid} where Id = {$aid}");
        
        return $ret;
    }

    /**
     *
     * 创建发布 状态 11
     *
     * @route POST /create
     * @hook \App\Hooks\BasicAuth
     * @param string $title 标题
     * @param string $type 类型 {@v in:image,video,rich}
     * @param int $uid {@bind request.headers.ClientUid}
     * @return string json:{success:1,Id:int,writetime:'yyyy-mm-dd HH:mm:ss'}
     */
    public function create($title, $type, $uid){

        $func = new \App\Utils\Func();
        $addr = $func->address();

        $data = [
            'title' => $title,
            'type' => $type,
            'authorId' => $uid,
            'status' => 11,
            'postAddr' => $addr['content']['address'],
            'cityCode' => $addr['content']['address_detail']['city_code'],
            'writetime' => date('Y-m-d H:i:s')
        ];

        $pdo = $this->db->getConnection();
        $pdo->exec('set names utf8mb4');

        $Id = $this->db->insertInto('t_articles')->values($data)->exec()->lastInsertId(); 
        
        $ret['ret'] = 1;
        $ret['msg'] = 'success';
        $ret['status'] = 11;
        $ret['Id'] = $Id;
        $ret['writetime'] = $data['writetime'];
        return $ret;
    }

    /**
     *
     * 更新发布状态 状态 1
     *
     * @route PUT /{aid}/created
     * @hook \App\Hooks\BasicAuth
     * @param string $medias [{"type":"image","name":"1.jpeg"},...]
     * @param string $content 正文
     * @param int $status 状态
     * @return string json:{ret:x, msg:1, status:x}
     */
    public function created($aid, $medias = '', $content = '', $status = 1){

        $pdo = $this->db->getConnection();
        $pdo->exec('set names utf8mb4');
       
        // 长贴文章 预发布(不先提交content)后得到ID， 生成OSS路径， 由客户端直接把资源以标签形式嵌入content, 更新发布状态时提交content
        if($content !== ''){
            //生成摘要
            $abstract = htmlspecialchars_decode($content);//把一些预定义的 HTML 实体转换为字符
            $abstract = str_replace(" ","",$abstract);//将空格替换成空
            $abstract = str_replace("&nbsp;","",$abstract);//将空格替换成空
            $abstract = strip_tags($abstract);//函数剥去字符串中的 HTML、XML 以及 PHP 的标签,获取纯文本内容
            if(strlen($abstract) > 100){
                $abstract = mb_substr($abstract, 0, 100) . '...';//返回字符串中的前100字符串长度的字符
            }

            if(strlen($abstract) < 5){
                $content .= '<p> ...这家伙太懒了,什么都没写</p>';
                $abstract .= ' ...这家伙太懒了,什么都没写';
            }

            $this->db->insertInto('t_articles_rich')
                ->values([
                    'Id'=> $aid,
                    'abstract'=>$abstract,
                    'content'=>$content,
                ])->exec();
        }

        $mediasJson = json_decode($medias);
        foreach($mediasJson as $m){
            $duration = 0;
            if ($m->type == 'video') {
                $duration = $m->duration;
            }
            $sql = "insert into t_articles_oss (AId, type, fname, duration) values ('{$aid}', '{$m->type}', '{$m->name}', '{$duration}')";
            $pdo->exec($sql);
        }

        $sql = "update t_articles set status = {$status} where Id = '${aid}'";
        if($status === 2){
            // 管理后台发布, 直接发布状态，不需要审核
            // 随机选择预设用户,前100名用户为机器人用户，专门发布文章用
            $authorId = mt_rand(1001, 1100);
            $sql = "update t_articles set authorId = {$authorId}, status = {$status} where Id = '${aid}'";
        } 
        
        $pdo->exec($sql);
        
        return [
            'ret' => 1,
            'msg' => 'success',
            'status'=> $status
        ];
    }

    /**
     *
     * 根据ID获取文章
     *
     * @route GET /{aid}
     * @param int $uid {@bind request.headers.ClientUid}
     */
    public function getByID($aid, $uid = -1){
        $pdo = $this->db->getConnection();
        $pdo->exec('set names utf8mb4');

        $sql = "select  
                t1.Id, t1.title, t1.type, t1.authorId, t1.writetime, t1.`status`, t1.postAddr,
                t2.nickname as authorname, t2.avatar,
                t3.content
        from t_articles t1 left outer join
            t_users t2 on t1.authorId = t2.uid left outer JOIN
            t_articles_rich t3 on t1.Id = t3.Id
        where t1.Id = {$aid}";
        $articles = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);        

        if($articles[0]['type'] !== 'rich'){
            // 资源
            $sql = "select AId, type, fname, duration from t_articles_oss where AId = {$aid}";
            $medias = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
            $articles[0]['videos']  = array(); 
            $articles[0]['images']  = array();                              
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
        }
        // liked??
        if ($uid > 0) {
            $sql = "select count(1) as cnt from t_articles_likes where AId = {$aid} and uid = {$uid}";
            $rst = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
            $articles[0]['liked'] = intval($rst[0]['cnt']) > 0 ? true : false;
        }

        $result['ret'] = 1;
        $result['msg'] = 'success';
        $result['article'] = $articles[0];
       
        return $result;
    }

    /**
     *
     * 指定用户的文章
     *
     * @route GET /{uid}/users 
     * @param int $client_uid {@bind request.headers.ClientUid}
     * @param int $page 分页页码
     * @param int $limit 分页分量
     */
    public function getUserArticles($page, $limit, $uid, $client_uid = -1){        
        $pdo = $this->db->getConnection();
        $pdo->exec('set names utf8mb4');

        $isMe = $uid == $client_uid;

        $count = 0;
        if($isMe && $page === 1){
            $sql = "select count(1) as cnt from t_articles where authorId = {$uid}";
            $rst = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
            $count = intval($rst[0]['cnt']);
        }
        
        $start = ($page-1) * $limit;
        $statusFilter = '';
        if(!$isMe) { // 如果不是自己，只能看正常和审核中的文章， 否则看全部
            $statusFilter = ' and t1.`status` in (1,2)';
        }
        $sql = "select t1.Id, t1.title, t1.type, t1.writetime, t1.authorId, t1.`status`, t1.lauds, t1.likes, t1.comments
        from t_articles t1 
        where t1.authorId = {$uid} {$statusFilter}
        order by writetime desc limit {$start},{$limit}";
        $articles = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
        $len = count($articles);
        
        if($len > 0){
            $Ids = '';
            for($i = 1; $i <= $len; $i++){
                $Ids = $Ids . $articles[$i-1]['Id'] . ($i === $len? '' : ',');               
            }

            // 资源
            $sql = "select AId, type, fname, duration from t_articles_oss where AId in ({$Ids})";
            $medias = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
            for($i = 0; $i < $len; $i++){
                $articles[$i]['videos']  = array(); 
                $articles[$i]['images']  = array();              
                foreach($medias as $media){
                    if($media['AId'] === $articles[$i]['Id']){
                        if($media['type'] === 'image'){
                            $articles[$i]['images'][] = $media['fname'];
                        }
                        if($media['type'] === 'video'){
                            $item['fname'] = $media['fname'];
                            $item['duration'] = $media['duration'];
                            $articles[$i]['videos'][] = $item;
                        }
                    }
                }
            }
        }
        $result['ret'] = 1;
        $result['msg'] = 'success';
        $result['count'] = $count;
        $result['list'] = $articles;
        return $result;
    }

    /**
     *
     * 用户收藏的文章
     *
     * @route GET /{uid}/likes
     * @hook \App\Hooks\BasicAuth     
     * @param int $page 分页页码
     * @param int $limit 分页分量
     */
    public function getUserlikes($uid, $page, $limit){
        $pdo = $this->db->getConnection();
        $pdo->exec('set names utf8mb4');
        
        $count = 0;
        if($page === 1){
            $sql = "select count(1) as cnt from t_articles_likes where uid = {$uid}";
            $rst = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
            $count = intval($rst[0]['cnt']);
        }

        $start = ($page-1) * $limit;
        $sql = "select t1.AId as Id, 
                t2.title, t2.type, t2.writetime, t2.authorId, IFNULL(t2.`status`, -1) as `status`,t2.lauds, t2.likes, t2.comments,
                t3.nickname as authorname
        from (
            select AId from t_articles_likes where uid = {$uid} order by writetime desc limit {$start},{$limit}
        )   t1 left outer join 
            t_articles t2 on t1.AId = t2.Id left outer join 
            t_users t3 on t2.authorId = t3.uid";
        $articles = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
        $len = count($articles);
        
        if($len > 0){
            $Ids = '';
            for($i = 1; $i <= $len; $i++){
                $Ids = $Ids . $articles[$i-1]['Id'] . ($i === $len? '' : ',');               
            }

            // 资源
            $sql = "select AId, type, fname from t_articles_oss where AId in ({$Ids})";
            $medias = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
            for($i = 0; $i < $len; $i++){
                $articles[$i]['videos']  = array(); 
                $articles[$i]['images']  = array();
                foreach($medias as $media){
                    if($media['AId'] === $articles[$i]['Id']){
                        if($media['type'] === 'image'){
                            $articles[$i]['images'][] = $media['fname'];
                        }
                        if($media['type'] === 'video'){
                            $item['fname'] = $media['fname'];
                            $item['duration'] = $media['duration'];
                            $articles[$i]['videos'][] = $item;
                        }
                    }
                }
            }
        }

        $result['ret'] = 1;
        $result['msg'] = 'success';
        $result['count'] = $count;
        $result['list'] = $articles;
        return $result;
    }

     /**
     *
     * 收藏文章
     *
     * @route PUT /{aid}/like
     * @hook \App\Hooks\BasicAuth
     * @param int $uid {@bind request.headers.ClientUid}
     */
    public function setlike($aid, $uid){
        $pdo = $this->db->getConnection();
        $sql = "insert into t_articles_likes (AId, uid) values ({$aid}, {$uid})"; // 数据库双主键，已规避重复收藏情况
        $result = $pdo->exec($sql);
        if($result === 1){
            $sql = "update t_articles set likes = likes + 1 where Id = {$aid}";
            $pdo->exec($sql);
        }        
        return ['ret' => 1, 'msg' => 'success'];
    }

    /**
     *
     * 删除收藏
     *
     * @route DELETE /{aid}/like
     * @hook \App\Hooks\BasicAuth
     * @param int $uid {@bind request.headers.ClientUid}
     */
    public function deletelike($aid, $uid){
        $pdo = $this->db->getConnection();
        $sql = "delete from t_articles_likes where AId = {$aid} and uid = {$uid}";
        $pdo->exec($sql);
        return ['ret' => 1, 'msg' => 'success'];
    }

     /**
     *
     * 文章点赞
     *
     * @route PUT /{aid}/laud
     */
    public function setlaud($aid){
        $pdo = $this->db->getConnection();
        $sql = "update t_articles set lauds = lauds + 1 where Id = {$aid}";
        $pdo->exec($sql);
        return ['ret' => 1, 'msg' => 'success'];
    }
} 