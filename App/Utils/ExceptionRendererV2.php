<?php
namespace App\Utils;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExceptionRendererV2
{
    /**
     * @param \Exception $e
     * @return Response
     */
    public function render(\Exception $e)
    {
        $ret['ret'] = -2;
        $ret['msg'] = $e->getMessage();
        if($ret['msg'] == ''){
            $ret['msg'] = get_class($e);
        }

        if($ret['msg'] == 'token_expired_verify') {
            $ret['ret'] = -99;
            $ret['msg'] = "登录信息过期,请重新登录";
        }

        $message = json_encode($ret, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $status = Response::HTTP_INTERNAL_SERVER_ERROR; // 500
        if($e instanceof HttpException){
            $status = $e->getStatusCode();
        } 

        return new Response($message, $status, ['Content-Type'=>'application/json']);
    }
}