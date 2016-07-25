<?php
namespace Process\Logic;
use Process\Logic\BillLogic;
use Common\Service\EmailService;
use Common\Service\SMSService;
/**
* author zhihui
* date 2016-4-26
* 账单逻辑处理类
*/

class HandleLogic{
	protected $db_process_nums = 20;
	/*
	* 获取需要的账单
	* 
	*/
	public function bill(){
		$this -> _billPay();
	}

	/**
	* 获取要处理的还款时间的列表
	*/
	public function _getDayDetail($index=0){
		$index = intval($index);
		if(!in_array($index, array(0,1,2))){
			$index = 0;
		}
		$day_arr = array();
		$current_day = date('Y-m-d');
		$day_arr[] = strtotime($current_day);
		$day_arr[] = strtotime($current_day.' -1 day');
		$day_arr[] = strtotime($current_day.' -2 day');
		return $day_arr[$index];
	}

	public function _billPay(){
		$day = $this->_getDayDetail(0);
		$this->_billData(1,$day);
		$this->_billProcess(1);
		$day = $this->_getDayDetail(1);
		$this->_billData(2,$day);
		$this->_billProcess(2);
		$day = $this->_getDayDetail(2);
		$this->_billData(3,$day);
		$this->_billProcess(3);
	}

	public function _billData($type=1,$day){
		$where = array('start_time'=>$day,'status' => 0);
		$nums = M('OrderBill')->where($where)->count();
		$loop = ceil($nums/$this->db_process_nums);
		//循环插入数据
		for($i =0 ; $i<$loop ; $i++){
			$limit = $i*$this->db_process_nums.','.$this->db_process_nums;

			$bill_data = M('OrderBill')->where($where)->limit($limit)->select();
			$insert_data = array();
			//获取拼接的数据
			foreach($bill_data as $data){
				$single_data = array(
					'type'=>$type,
					'bill_info' => json_encode($data,JSON_UNESCAPED_UNICODE),
					'status' => 1,
					'created_time' => time(),
					);
				$insert_data[] = $single_data; 
			}
			//bill buffer 数据入库
			$is = M('BillBufferData')->addAll($insert_data); 
		}
	}

	public function _billProcess($step=1){
		$where = array('status'=>1,'type'=>$step);
		$nums = M('BillBufferData')->where($where)->count();
		$loop = ceil($nums/$this->db_process_nums);
		for($i=0;$i<$loop;$i++){
			$limit = $i*$this->db_process_nums.','.$this->db_process_nums;
			$buffer_data = M('BillBufferData')->where($where)->limit($limit)->select();
			foreach($buffer_data as $data){
				$bill_info = json_decode($data['bill_info'],true);
				$data['status'] = 2;
				M('BillBufferData') ->save($data);
				$handle_insert_data = array();
				$frequency = 1;
				$handle_status = 0;
				$email_frequency = 0;
				$sms_frequency = 0;
				$handle_info = array();
				switch($step){
					case 1:
						$sms_frequency = 1;
						$bl = new BillLogic();
						$ret = $bl->repaymentData($bill_info);
						$ret['time'] = date('Y-m-d H:i:s');
						$ret['type'] = $step;
						$handle_status = $ret['status']?1:2;
						$sms = new SMSService();
        				$user = M('Users')->where('id='.$bill_info['user_id'])->find();
						if($ret['status']){
							$sms_ret = $sms -> sendMonthBill($user['mobile']);
							$sms_ret = $sms -> sendBackPayBill(array(
                				'mobile' => $user['mobile'],
                				'date' => date('Y-m-d'),
                				'rent_price' => $bill_info['rent_price'],
                				'price' => $ret['balance'],
                				));
						}else{
							$sms_ret = $sms -> sendMonthBill($user['mobile']);
						}
						$ret['msg'] .= '短信发送 - '.$sms_ret;
						//$handle_insert_data['email_frequency'] = $email_frequency;
						//$handle_insert_data['sms_frequency']  = $sms_frequency;
						//$handle_insert_data['status'] = $handle_status;
						$handle_info[] = $ret;
						break;
					case 2:
						$sms = new SMSService();
            			//获取用户手机号
						$user = M('Users')->where('id='.$bill_info['user_id'])->find();
						$order_info = M('GoodsOrder')->find($bill_info['order_id']);
            			$sms_ret = $sms -> sendBackPayInformBill(array(
                			'mobile' => $user['mobile'],
                			'order_no' => $order_info['inner_trade_no'],
                			'sequence' => $bill_info['sequence'],
                			'price' => $bill_info['rent_price'],
                		));
						//$email_frequency = 0;
						$sms_frequency = 1;
						$handle_info[] = array(
								'time' => date('Y-m-d H:i:s'),
								'type' => $step,
								'msg' => '已发送短信账单提醒'
							);
						break;
					case 3:
						$order_info = M('GoodsOrder')->find($bill_info['order_id']);
						//获取用户用户信息
						$user = M('Users')->where('id='.$bill_info['user_id'])->find();
						$user_account = M('UserAccounts')->where('id='.$bill_info['user_id'])->find();
						if(!empty($user['email'])){
							$email = new EmailService();
							$flag = $email -> billWarning(array(
									'email' => $user['email'],
									'order_no' => $order_info['inner_trade_no'],
									'sequence' => $bill_info['sequence'],
									'rent_price' => $bill_info['rent_price'],
									'rent_start' => date('Y-m-d',$bill_info['start_time']),
									'rent_end' => date('Y-m-d',$bill_info['end_time']),
									'balance'  => '￥'.$user_account['balance'],
									'username' => $user['user_name']?$user['user_name']:$user['mobile'],
									'date' => date('Y年m月'),
									'url' => 'http://www.kuaizu365.cn/home/account/bill.html',
								));
							$handle_info[] = array(
								'time' => date('Y-m-d H:i:s'),
								'type' => $step,
								'msg' => '已发送邮件提醒'
							);
							$email_frequency = 1;
						}else{
							$sms = new SMSService();
            				//获取用户手机号
	            			//$sms_ret = $sms -> sendMonthBill($user['mobile']);
	            			$msg = '账单出账单'.date('Y年m月').';订单号：'.$order_info['inner_trade_no'].' ;当前第期'.$bill_info['sequence'].'还款，租期 '.date('Y年m月d日',$bill_info['start_time']).'到'.date('Y年m月d日',$bill_info['end_time']).'，租金:'.$bill_info['rent_price'];
	            			//$msg = '账单出账单'.date('Y年m月').';订单号：'.$order_info['inner_trade_no'].'：当前第期'.$bill_info['sequence'].'还款，租期 ';
	            			$sms_ret = $sms->sendGeneral(array(
	            					'mobile' => $user['mobile'],
	            					'msg'=> $msg,
	            				));
	            			$handle_info[] = array(
								'time' => date('Y-m-d H:i:s'),
								'type' => $step,
								'msg' => '已发送短信提醒,未绑定邮箱则短信提醒'
							);
	            			$sms_frequency = 1;
						}
						/*$handle_info[] = array(
								'time' => date('Y-m-d H:i:s'),
								'type' => $step,
								'msg' => '已发送邮件提醒,未绑定邮箱则短信提醒'
							);*/
						break;
				}

				$bill_handle_data=M('BillHandleData')->where('bill_id='.$bill_info['id'])->find();
				if(empty($bill_handle_data)){
					$handle_insert_data = array(
						'bill_id' => $bill_info['id'],
						'user_id' => $bill_info['user_id'],
						'order_id' => $bill_info['order_id'],
						'bill_price' => $bill_info['rent_price'],
						);
					$handle_insert_data['frequency'] = $frequency;
					$handle_insert_data['email_frequency'] = $email_frequency;
					$handle_insert_data['sms_frequency']  = $sms_frequency;
					$handle_insert_data['status'] = $handle_status;
					$handle_insert_data['handle_info_serial'] = json_encode($handle_info,JSON_UNESCAPED_UNICODE);
					$handle_insert_data['created_time'] = time();
					M('BillHandleData')->add($handle_insert_data);
				}else{
					$bill_handle_data['frequency'] += $frequency;
					$bill_handle_data['email_frequency'] += $email_frequency;
					$bill_handle_data['sms_frequency'] += $sms_frequency;
					$bill_handle_data['status'] = $handle_status;
					$handle_info_serial = json_decode($bill_handle_data['handle_info_serial'],true);
					if(is_array($handle_info_serial)){
						$handle_info_serial = array_merge($handle_info_serial,$handle_info);
						$bill_handle_data['handle_info_serial'] = json_encode($handle_info_serial,JSON_UNESCAPED_UNICODE);
					}else{
						$bill_handle_data['handle_info_serial'] = json_encode($handle_info,JSON_UNESCAPED_UNICODE);
					}
					M('BillHandleData')->save($bill_handle_data);
				}

			}
		}
	}

	//入bill_handle_data 数据
	public function _billHandleData(){

	}
/**
insert into order_bill(bill_no,order_id,user_id,sequence,rent_periods,rent_price,quantity,start_time,end_time,status,created_time) 
values('ehqgr1kcfv',177,1,8,24,808.00,2,1461600000,1464192000,1,unix_timestamp());

*/
}