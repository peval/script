<?php
/**
 * User: zhihui
 * Date: 16-4-24
 * Time: 下午8:54
 * sendCloud 发送邮件
 */
namespace Common\Service;
use Common\Api\GearmanApi;
class EmailService {
    // configuration
    private $config;
    // 加密逻辑
    private $verify;

    public function __construct()
    {
        $this->gearmanApi = new GearmanApi();
    }

    /**
     * 发送邮件
     * @param array $to 接收邮件地址列表
     * @param array $substitution 模板变量替换数据
     * @param string $subject 邮件标题
     * @param string $template_name 使用模板的标识
     * @return mixed
     */
    private function send(array $to, array $substitution, $subject, $template_name){
        $email_params = array(
            'substitution' => $substitution,
            'subject'  => $subject,
            'template_name' => $template_name,
            //'to' => '704385454@qq.com'//array('704385454@qq.com')
            'to' => $to
        );
        $flag = $this ->gearmanApi->sendEmail($email_params);
        return $flag;
    }

    //数据整理
    public function delivery($to, $substitution, $subject, $template_name){
        if(!is_array($to) || !is_array($substitution) || empty($subject) || empty($template_name)){
            return false;
        }

        //发送邮件
        $ret = $this->send($to, $substitution, $subject, $template_name);
        return $ret;
    }

    /**
     * 用户绑定邮箱，验证邮件
     * @param int $user_id 用户id列表，加密链接使用
     * @param string $to 接收者邮件地址 : "test@test.com"
     * @param string $name
     * @return mixed
     */
    public function bindAddress($user_id, $to, $name){

        // base url
        $url = U("Verify/emailBind", "", false, true);
        // params
        $params = array("user_id" => $user_id, "email_address" => $to);
        // generating token based on url params
        $url.="?".http_build_query($params);
        $token_salt = "SeTN3gYN$#K!";
        // 排序
        ksort($params, SORT_FLAG_CASE | SORT_STRING);
        // 生成token
        $token = md5(implode("", $params).$token_salt);
        $url .= "&token=".$token;
        // 替换数据
        $substitution = array("%name%" => [$name], "%url%" => [$url]);
        // 邮件主题
        $subject = "绑定邮箱";
        // 模板名称
        $template_name = "kuaizu_user_bind_email";

        // 发送邮件
        return $this->delivery([$to], $substitution, $subject, $template_name);
    }

    public function test($email){
        return $this->delivery([$email],array(),'账单提醒123','kuaizu_new_order');
    }

    /**
    *  账单到期付款提醒
    */
    public function billWarning($arr){
        $substitution = array();
        $substitution['%order_no%'] = array($arr['order_no']);
        $substitution['%sequence%'] = array($arr['sequence']);
        $substitution['%rent_price%'] = array($arr['rent_price']);
        $substitution['%balance%'] = array($arr['balance']);
        $substitution['%username%'] = array($arr['username']);
        $substitution['%date%'] = array($arr['date']);
        $substitution['%rent_start%'] = array($arr['rent_start']);
        $substitution['%rent_end%'] = array($arr['rent_end']);
        $substitution['%url%'] = array($arr['url']);

        $subject = '快租365租赁账单('.$arr['date'].'）';
        return $this->delivery([$arr['email']],$substitution,$subject,'kuaizu_bill_warn');
    }

}