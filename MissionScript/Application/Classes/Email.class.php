<?php
namespace Application\Classes;
class Email{
    // configuration
    private $config = array(
        	"API_USER" => "kuaizu_365_trigger",
        	"API_KEY" => "cV1VXUsoKQuXWG10",
        	"FROM" => "info@kuaizu365.cn",
        	"FROMNAME" => "kuaizu365",
        	"REPLYTO" => "reply@kuaizu365.cn",
        	"URL"    => "http://sendcloud.sohu.com/webapi/mail.send_template.json?",
        	);

    public function __construct(){
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
        $email['api_user'] = $this->config['API_USER'];
        $email['api_key'] = $this->config['API_KEY'];
        $email['from'] = $this->config['FROM'];
        $email['fromname'] = $this->config['FROMNAME'];
        $email['replyto'] = $this->config['REPLYTO'];
        $url = $this->config['URL'];

        $vars = ['to' => $to , "sub" => $substitution];
        $email['substitution_vars'] = json_encode($vars, JSON_UNESCAPED_UNICODE);
        $email['subject'] = $subject;
        $email['template_invoke_name'] = $template_name;
        $email['resp_email_id'] = true;
        $url .= http_build_query($email);

        $resource = curl_init();
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
        );
        curl_setopt_array($resource, $options);

        $feedback = curl_exec($resource);
        curl_close($resource);
        /*
        // 解析返回
        $feedback = json_decode($feedback, true);
        // 输出错误
        if($feedback['message'] == "error"){
            $mc = MessageContainer::getInstance();
            $mc->setErrorCode("sendCloud");
            $mc->setErrorMsg($feedback['errors']);
            return false;
        }else{
            return true;
        }
        */
    }

    public function request($data){
    	$this->send($data['to'], $data['substitution'], $data['subject'], $data['template_name']);
    }
}