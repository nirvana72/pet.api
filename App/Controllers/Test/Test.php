<?php
namespace App\Controllers\Test;
use PhpBoot\DI\Traits\EnableDIAnnotations;
use Symfony\Component\HttpFoundation\Request;
use App\Hooks\BasicAuth;
use PhpBoot\DB\DB;

/**
 * Test - 测试
 *
 * @path /test/test
 */
class Test
{
    use EnableDIAnnotations; //启用通过@inject标记注入依赖

    /**
     * @inject
     * @var Request
     */
    private $request;

    /**
     * @inject
     * @var DB
     */
    private $db;    

    /**
     *
     * 测试Test
     *
     * @route GET /
     
     * @return string 结果
     */
    public function index(){
        $func = new \App\Utils\Func();
        $addr = $func->address();
        return $addr;// ['content']['address'];
        /**
        $sql = "select Id from t_articles";
        $pdo = $this->db->getConnection();
        $result = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
        shuffle($result);
        $writetime = date("Y-m-d H:i:s");
        foreach($result as $row){
            $mm = mt_rand(1000, 10000);
            // $newID = $index * 10;
            $writetime = date('Y-m-d H:i:s', strtotime ("-{$mm} seconds", strtotime($writetime)));
            $sql = "update t_articles set writetime='{$writetime}' where Id={$row['Id']};";
            $pdo->exec($sql);
        }
        return 1;

        /**
        $pdo = $this->db->getConnection();
        $pdo->exec('set names utf8mb4');
        $sql = "select Id, content from t_articles_rich";
        $result = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
        foreach($result as $row){
            $content = $row['content'];
           
            $abstract = htmlspecialchars_decode($content);//把一些预定义的 HTML 实体转换为字符
            $abstract = str_replace(" ","",$abstract);//将空格替换成空
            $abstract = str_replace("&nbsp;","",$abstract);//将空格替换成空
            $abstract = strip_tags($abstract);//函数剥去字符串中的 HTML、XML 以及 PHP 的标签,获取纯文本内容
            if(strlen($abstract) > 100){
                $abstract = mb_substr($abstract, 0, 100) . '...';//返回字符串中的前100字符串长度的字符
            }            
            $sql = "update t_articles_rich set abstract = '{$abstract}' where Id = {$row['Id']}";
            $pdo->exec($sql);
        }
        return 1;
        


        $pdo = $this->db->getConnection();
        // $pdo->exec('set names utf8mb4');
        $stmt = $pdo->prepare("insert into t_test(`name`) values (:name)");           
        $stmt->bindParam(':name', '制egwewgewg', PDO::PARAM_STR);
        $result = $stmt->execute();
        return $result;

 
        $pdo = $this->db->getConnection();
        $pdo->exec('set names utf8mb4');
        $sql = "insert into t_test(`name`) values ('制)↑返回顶部 😀😁😂😃😄😅😆😉😊😋...')";
        return $pdo->exec($sql);*/
/*
        // $uid = $this->request->headers->get('ClientUid');
        if(preg_match("/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]{6,18}$/u", '中文名中文名中文名中文名')){
            return 1;
        }
        return 2;*/
    }

    /**
     *
     * 测试Test
     *
     * @route GET /sendMessage
     
     * @return string 结果
     */
    public function sendMessage(){
        //请求地址
        $uri = "http://cdn-hangzhou.goeasy.io/goeasy/publish";
        // 参数数组
        $data = [
            'appkey'  => "BC-461b5d45a45a4475bd967fa1b40b3645",
            'channel' => "demo",
            'content' =>"您有新的订单"
        ];
        $ch = curl_init ();
        curl_setopt ( $ch, CURLOPT_URL, $uri );//地址
        curl_setopt ( $ch, CURLOPT_POST, 1 );//请求方式为post
        curl_setopt ( $ch, CURLOPT_HEADER, 0 );//不打印header信息
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );//返回结果转成字符串
        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );//post传输的数据。
        $return = curl_exec ( $ch );
        curl_close ( $ch );
        print_r($return);
    }

    /**
     *
     * 测试Test
     *
     * @route GET /calltest
     
     * @return string 结果
     */
    public function calltest(){
        $api = new \App\Controllers\App\RongCloudApi();
        return $api->sendUserMessage(1111, 'hello');
    }
} 