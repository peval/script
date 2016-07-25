<?php
namespace Common\Service;
use Common\Api\GearmanApi;
/**
 * 短信类
 */

class SMSService {
    // 短信发送开关,false - 模拟测试短信
    private $is_on;
    private $gearmanApi = null;

    /**
     * 构造函数,从配置文件读取开关
     */
    public function __construct(){
        $this->is_on = true;
        $this->gearmanApi = new GearmanApi();
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
            return "testing";
        }
        $params = array('phone' => $phone,'msg' => $msg);
        $flag = $this ->gearmanApi->sendMessage($params);
        if(!$flag){
            return 'error';
        }
        return 'distributed';
    }

    /**
     * 发送短信包括记录日志
     * 该方法会调用send()方法发送短信,然后调用log()方法记录日志
     * @param string $mobile 用户手机号码
     * @param string $template_id 模板id
     * @param array $var 用于填充模板的数据,具体细节见send()函数
     * @return boolean
     * @see send()
     */
    public function delivery($mobile, $msg){
        $feedback = $this->send($mobile, $msg);
        //$this->log($mobile, $msg, $feedback);
        //$this->data($mobile, $msg, $feedback);
        //return ($feedback == 'testing') || (strpos($feedback, 'id=') !== false);
        if($feedback == 'testing' || $feedback == 'distributed'){
            return true;
        }
        if($feedback == 'error'){
            return false;
        }
    }

    /**
     * 短信数据记录
     * @param string $phone 手机号码
     * @param string $msg JSON格式的模板填充数据
     * @param string $feedback JSON格式的短信API返回数据
     * @return mixed
     */
    public function data($phone, $msg, $feedback){
        $sms_data = D("SmsData");
        $log['mobile'] = $phone;
        $log['msg'] = $msg;
        $log['send_time'] = time();
        $log['user_ip'] = Validation::getUserIP();
        $log['feedback'] = $feedback;
        $log['status'] =1;
        $log['request_info'] = json_encode($_SERVER,JSON_UNESCAPED_UNICODE);
        $sms_data->create($log);
        return $sms_data->add();
    }


    /**
     * 发送验证码
     * @param string $mobile 手机号码
     * @return mixed
     */
    public function sendVerification($mobile){
        $msg = '您的验证码是{code}，打死也不能告诉别人哦';
        $verify_code = Validation::makeVerifyCode($mobile, 6);
        $msg = str_replace("{code}", $verify_code, $msg);
        return $this->delivery($mobile, $msg);
    }

    /**
     * 月账单-消息提醒
     * @param $mobile
     * @return bool
     */
    public function sendMonthBill($mobile){
        $template = "您好！您当月的账单已经下发，请及时登录账户查看、支付，以免逾期影响您的信用星级。";
        $msg = $template;
        return $this->delivery($mobile,$msg);
    }

    /**
    * 账单自动扣款通知
    */
    public function sendBackPayBill($arr){
        $template = "您好！系统于{date}自动从您账户中扣除本月租金{rent_price}元，账户现余额{price}元";
        $msg = str_replace(array('{date}','{rent_price}','{price}'),array($arr['date'],$arr['rent_price'],$arr['price']),$template);
        return $this->delivery($arr['mobile'],$msg);
    }

    /*账单提醒扣款*/

    public function sendBackPayInformBill($arr){
        $template = "您好！你的订单{order_no},第{sequence}期该月的账单已经下发，请保证账户余额充足，扣款金额：￥{price}";
        $msg = str_replace(array('{order_no}','{sequence}','{price}'),array($arr['order_no'],$arr['sequence'],$arr['price']),$template);
        return $this->delivery($arr['mobile'],$msg);

    }
    public function sendGeneral($arr){
        $template = '您好!{msg}';
        $msg = str_replace('{msg}',$arr['msg'],$template);
        return $this->delivery($arr['mobile'],$msg);
    }

}
