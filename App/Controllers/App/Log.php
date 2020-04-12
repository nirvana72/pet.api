<?php
namespace App\Controllers\App;
use PhpBoot\DI\Traits\EnableDIAnnotations;
use PhpBoot\DB\DB;
/**
 * APP - Log
 *
 * @path /app/log
 */
class Log
{
    use EnableDIAnnotations; //启用通过@inject标记注入依赖

    /**
     * @inject
     * @var DB
     */
    private $db;

    /**
     *
     * 举报
     *
     * @route PUT /report
     * @param int $article_id article_id
     * @param int $report_uid report_uid
     * @param string $content content
     */
    public function report($article_id, $report_uid, $content){  

        $this->db->insertInto('log_report')
            ->values([
                'article_id' => $article_id,
                'report_uid' => $report_uid,
                'content' => $content,
            ])->exec();

        return 1;
    }
} 