<?php
namespace Process\Controller;
use Think\Controller;
use Process\Logic\HandleLogic;
use Process\Logic\CancelLogic;
class HandleController extends Controller {
	//账单计算入口
	public function calculate(){
		$hl = new HandleLogic();
		$hl->bill();
	}

	//未支付订单取消入口
	public function cancel(){
      	$cl = new CancelLogic(); 
      	$cl->cancel();
    }

    //自动收货处理
      //个人中心用户确认收货操作
    public function deliveryConfirm(){
    	exit('operate is forbidden!');
        $order_id = I('get.order_id');
        if(empty($order_id) || !is_numeric($order_id)){
            ujson(array('msg'=>'参数错误','status'=>false));
        }
        $order = D('GoodsOrder')->getByUserIdAndId($order_id,$this->user['id']);
        if(empty($order)){
            ujson(array('msg'=>'订单数据异常','status'=>false));
        }
        if($order['status'] != 2){
            ujson(array('msg'=>'状态错误','status'=>false));
        }
        $ol = new OrderLogic();
        $data = array(
            'status'=>3,
            'start_time' =>time(),
            'end_time' =>$ol->rentEnd(time()),
            );
        M()->startTrans();
        $flag = M('GoodsOrder')->where('id='.$order['id'])->save($data);
        $order = M('GoodsOrder')->where('id='.$order['id'])->find();
        $ret = $ol->createOrderBill($order);
        if($flag && $ret){
            M()->commit(); 
            ujson(array('msg'=>'操作成功','status'=>true));
        }else{
            M()->rollback();
            ujson(array('msg'=>'操作失败','status'=>false));
        }
    }
	public function smstest(){
		$sms = new \Common\Service\SMSService();
        $user = M('Users')->where('id=1')->find();
		/*if($ret['status']){
			$sms_ret = $sms -> sendMonthBill($user['mobile']);
			$sms_ret = $sms -> sendBackPayBill(array(
            				'mobile' => $user['mobile'],
              				'date' => date('Y-m-d'),
               				'rent_price' => $bill_info['rent_price'],
               				'price' => $ret['balance'],
              				));
		}else{*/
		$sms_ret = $sms -> sendMonthBill($user['mobile']);
		var_dump($sms_ret);
	}

	public function emailtest(){
		/*$email = new \Common\Service\EmailService();
		$flag = $email ->bindAddress(1, '704385454@qq.com', 'zhihui');
		var_dump($flag);
		return;*/
		$sub = array(
	    	'email' => '704385454@qq.com',
	    	'order_no' => '14615660058503',
	    	'sequence' => 8,
	    	'rent_price' => 808.00,
	    	'rent_start' => '2016-04-26',
	    	'rent_end' => '2016-04-26',
	    	'balance' =>12,
	    	'username' => 'zhihui',
	    	'date' => '2016-04',
	    	'url' => 'http://www.kuaizu365.cn/home/account/bill.html'
		);
		$email = new \Common\Service\EmailService();
		$flag = $email ->billWarning($sub);
		var_dump($flag);

	}
}