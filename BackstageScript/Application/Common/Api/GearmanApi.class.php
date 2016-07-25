<?php
/*
 * 分布式解决方案
 * */
namespace Common\Api;
class GearmanApi{
    private $host = '139.196.241.46';
    private $port = '4730';
    private $connect = true;
    private $gmclient = null;
    public function __construct()
    {
        //实例化分布式客户端连接
        $this->gmclient = new \GearmanClient();
        try {
            $this->gmclient->addServer($this->host, $this->port);
        }catch(\Exception $e){
            $this->connect = false;
        }
    }

    /*
     * 短信发送请求
     * @parmas  $data = array('phone'=>'要发送的短信号码','msg'=>'短信消息内容');
     * */
    public function sendMessage($data){
        if($this->connect == false){
            return false;
        }
        $json_data = json_encode($data,JSON_UNESCAPED_UNICODE);
        $this->gmclient->doBackground("kuaizu365_type_message",$json_data);
        return true;
    }

    /*
   * 短信发送请求
   * @parmas  $data = array('phone'=>'要发送的短信号码','msg'=>'短信消息内容');
   * */
    public function sendEmail($data){
        if($this->connect == false){
            return false;
        }
        $json_data = json_encode($data,JSON_UNESCAPED_UNICODE);
        $this->gmclient->doBackground("kuaizu365_type_email",$json_data);
        return true;
    }
}