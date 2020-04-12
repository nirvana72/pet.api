<?php
namespace App\ControllersV2\Server;
require_once __DIR__ . "/../../../RongCloud/RongCloud.php";
/**
 * APP - Server
 *
 * @path /app/server
 */
class Index
{
    /**
     *
     * 安卓版本
     *
     * @route GET /version
     */
    public function version(){  
        $ret['version'] = '1.0.5.1';
        $ret['apkurl'] = 'https://www.mu78.com/apk/pethobby1.0.5.1.apk';
        $ret['info'] = [            
            '修复了部分苹果发布的视频不能播放的问题',
            '修复了一些已知的问题',
        ];        
        return $ret;
    }

    /**
     *
     * 测试用
     *
     * @route GET /
     * @param int $ret ret
     * @param string $msg msg
     */
    public function test($ret = 1, $msg = 'success'){  
        return ['ret'=>$ret,'msg'=>$msg];
    }

    /**
     *
     * 融云IM发测试消息
     *
     * @route PUT /im
     * @param int $fromUserId fromUserId
     * @param int $toUserId toUserId
     * @param string $msg msg
     */
    public function im($fromUserId, $toUserId, $msg = 'success'){  
        
        $APPKEY = 'mgb7ka1nmdugg';
        $APPSECRET = '6yCGuI2lQF';

        // 发送系统消息
        $RongSDK = new \RongCloud\RongCloud($APPKEY, $APPSECRET);
        $message = [
            'senderId'=> $fromUserId,//发送人 id
            'targetId'=> $toUserId,//接收放 id
            "objectName"=>'RC:TxtMsg',//消息类型 文本
            'content'=>json_encode(['content'=>$msg,'extra'=>'小明'])//消息体
        ];
        $Result = $RongSDK->getMessage()->Person()->send($message);
        return $Result;
    }
} 