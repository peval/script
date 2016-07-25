<?php
namespace Application\Classes;
class Message{
	 // 短信发送开关,false - 模拟测试短信
    private $is_on = true;
    private $SMS_ACCOUNT='N13764199088';
    private $SMS_PASSWORD='168322';
    private $SMS_URL = 'http://222.73.117.138:7891/mt';
    private $SMS_TITLE = "【快租365】";


    /**
     * 构造函数
     */
    public function __construct(){
        //$this->is_on = C("SMS_SWITCH");
        //$this->is_on = true;
    }
    /**
     * 发送短信逻辑
     * @param string $phone 手机号码
     * @param string $msg 编码之前的发送消息
     * @return mixed
     * 该方法只负责发送短信,不会记录日志
     * 所以
     * 为了保证短信日志的完整性,尽量不要使用delivery方法之外的方法调用该方法
     */
    private function send($phone, $msg){
        if(!$this->is_on){
            // 模拟发送
            return ;
        }
        // 追加签名在消息头部
        $msg = $this->SMS_TITLE.$msg;
        // 16进制编码
        $msg = $this->strToHex($msg);
        $url_params = array();
        $url_params['un'] = $this->SMS_ACCOUNT;
        $url_params['pw'] = $this->SMS_PASSWORD;
        $url_params['da'] = $phone;
        $url_params['sm'] = $msg;
        $url_params['dc'] = 15;
        $url_params['rd'] = 1;
        $o="";
        foreach ($url_params as $k=>$v)
        {
            $o.= "$k=".urlencode($v)."&";
        }
        $url_params=substr($o,0,-1);
        $request_url = rtrim($this->SMS_URL,'?').'?'.$url_params;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPGET, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL, $request_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        return $result;
    }

    /*
	* 发送请求信息
	* author zhihui
	* date 2016-4-21
    */
    public function request($data){
    	$this->send($data['phone'], $data['msg']);
    }

    /**
     * 短信内容转为16进制
     * @param string $string 加上签名头部的短信内容
     * @return string
     */
    public function strToHex($string){
        $string = iconv('utf-8','gbk',$string);
        $hex="";
        for($i=0;$i<strlen($string);$i++)
            $hex.=dechex(ord($string[$i]));
        return $hex;
    }

}