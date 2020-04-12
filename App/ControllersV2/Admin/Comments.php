<?php
namespace App\ControllersV2\Admin;
use PhpBoot\DI\Traits\EnableDIAnnotations;
use App\Hooks\BasicAuth;
use PhpBoot\DB\DB;
use Psr\Log\LoggerInterface;

/**
 * Admin - 评论
 *
 * @path /admin/comments
 */
class Comments
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
   * 评论列表
   *
   * @route GET /
   * @hook \App\Hooks\BasicAuth
   * @param int $page 分页页码
   * @param int $limit 分页分量
   * @param string $day 发表日期
   */
  public function getList($page=1, $limit=10, $day=''){
    $pdo = $this->db->getConnection();
    $pdo->exec('set names utf8mb4');

    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$day)){
      $day = date("Y-m-d");
    }

    $sql = "select count(1) as cnt from t_comments where writetime like '{$day}%'";        
    $result = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);
    $ret['count'] = intVal($result[0]['cnt']);

    $start = ($page-1) * $limit;
    $sql = "select Id, content, writetime, `status`, uid from t_comments where writetime like '{$day}%' order by writetime desc limit {$start},{$limit}";
    $ret['list'] = $pdo->query($sql)->fetchAll(\PDO::FETCH_NAMED);

    $ret['ret'] = 1;
    $ret['msg'] = 'success';
    return $ret;
  }

  /**
   *
   * 垃圾评论
   *
   * @route PUT /{Id}/reject
   * @hook \App\Hooks\BasicAuth
   */
  public function reject($Id){   
    $sql = "update t_comments set `status` = 0 where Id = {$Id}";
    $pdo = $this->db->getConnection();
    $pdo->exec($sql);
    return ['ret' => 1, 'msg'=> 'success'];
  }
} 