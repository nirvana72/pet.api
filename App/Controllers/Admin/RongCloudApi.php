<?php
namespace App\Controllers\Admin;
require_once __DIR__ . "/../../../RongCloud/RongCloud.php";
use App\Hooks\BasicAuth;

/**
 * Admin - RongCloudApi
 *
 * @path /admin/RongCloudApi
 */
class RongCloudApi
{
    private $APPKEY = 'mgb7ka1nmdugg';
    private $APPSECRET = '6yCGuI2lQF';

    /**
     *
     * 发送用户消息
     *
     * @route GET /sendUserMessage
     * @hook \App\Hooks\BasicAuth
     * @param int $uid uid
     * @param string $msg msg
     */
    public function sendUserMessage($uid, $msg){  
        $RongSDK = new \RongCloud\RongCloud($this->APPKEY, $this->APPSECRET);
        $message = [
            'senderId'=> '1000',//发送人 id
            'targetId'=> $uid,//接收放 id
            "objectName"=>'RC:TxtMsg',//消息类型 文本
            'content'=>json_encode(['content'=>$msg,'extra'=>'系统消息'])//消息体
        ];
        $Result = $RongSDK->getMessage()->Person()->send($message);
        return $Result;
        // Utils::dump("系统消息发送",$Result);
    }
} 