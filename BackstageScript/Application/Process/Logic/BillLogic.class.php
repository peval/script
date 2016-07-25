<?php
namespace Process\Logic;
use Common\Service\SMSService;
/*
  账单还款成功回调处理
    */
class BillLogic{


    public function billPay($bill_info){
        $trade_no = create_bill_trade_no();
        if(!$this->_bill_insert_no($bill_info['id'],$trade_no)){
            return array('status'=>false,'msg'=>'更新支付单号失败');
        }
        //生成支付记录
        $bill_info = array_merge($bill_info,array('trade_no'=>$trade_no));
        $ret = $this->_insert_pay_info($bill_info);

        $this->_goPay($bill_info);
    }

    //数据更新
    public function repaymentData($bill_info){

        //通过账单支付单号查询对应还款的记录
        //$bill_info = D('OrderBill')->getNoPayByOrderId($order_id);
        if(empty($bill_info) || !is_array($bill_info)){
            return array(
                'status'=>false,
                'msg' => 'bill_info 为空'
                );
        }
        if($bill_info['status'] != 0){
            return array(
                'status'=>false,
                'msg' => 'bill_info的status不为0'
                );
        }
        //更新账单及订单状态
        M()->startTrans();
        //账单还款金额变动
        $order_info = M('GoodsOrder')->find($bill_info['order_id']); //查询商品的订单数据
        //通过购买商品的订单查询购买的用户的免押金数据
        $user_price = M('UserAccounts')->where('user_id='.$bill_info['user_id'])->find();

        if($user_price['balance'] < $bill_info['rent_price']){
            return array(
                'status' => false,
                'msg'  => '账户余额不足',
                );
        }

        //扣除账户资金
        $user_balance = $user_price['balance']-$bill_info['rent_price']; //用户的账户余额
        $account_ret = M('UserAccounts')->where('id='.$user_price['id'])->save(array('balance'=>$user_balance));

        //金额变动记录

        $flow_ret = M('MoneyFlow')->add(array(
                'user_id' =>$bill_info['user_id'],
                'type' => 1,
                'des'  => '账单后台扣款支付租金',
                'trade_des'  => '订单账单支付，账单id为'.$bill_info['id'],
                'order_id' => $bill_info['id'],
                'price' => $bill_info['rent_price'],
                'created_time' =>time(),
            ));
        //账单扣款记录
        $log_ret= M('BillPay')->add(array(
                'bill_id' =>$bill_info['id'],
                'price' => $bill_info['rent_price'],
                'type' =>2,
                'created_time' => time(),
            ));

        if(!$account_ret || !$flow_ret || !$log_ret){
            M()->rollback();
            return array(
                'status' => false,
                'msg'  => '账单扣款失败'
                );
        }

        //$ret_bill = M('OrderBill')->where('id='.$bill_info['id'])->save(array('pay_time'=>time(),'status'=>1));
        $ret_bill = M('OrderBill')->where('id='.$bill_info['id'])->save(array('pay_time'=>time(),'status'=>1));

        $ret_order = true;
        //通过 订单单号（order_id） 获取全部未付款的账单
        $bill_no_pay = D('OrderBill')->getNoPayByOrderId($bill_info['order_id']);



        //没有余下还款了
        if(empty($bill_no_pay)){
            //$order_info = M('GoodsOrder')->find($bill_info['order_id']); //查询商品的订单数据
            //$user_price = M('UserAccounts')->where('user_id='.$order_info['user_id'])->find();//通过购买商品的订单查询购买的用户的免押金数据
            //订单不存在则错误
            if(empty($bill_info)){
                $ret_order = false;
            }else{
                //订单返回则出现 返回商品
                //订单商品租赁完成是否返回商品 is_return  1，返回，2，不返回'
                if($bill_info['is_return'] = 2){
                    //订单完成
                    $ret_order = M('GoodsOrder')->where('id='.$bill_info['order_id'])->save(array('status'=>5));
                }else{
                    //还款完成
                    $ret_order = M('GoodsOrder')->where('id='.$order_info['order_id'])->save(array('status'=>4));
                }
                //退还押金
                //判断是否使用了免押金
                if($order_info['is_use_credit']==1){
                    M('UserAccounts')->where('user_id='.$order_info['user_id'])->save(array('credit_price'=>$user_price['credit_price']+$order_info['deposit_price']));
                    M('UserAccounts')->where('user_id='.$order_info['user_id'])->save(array('frozen_price'=>$user_price['frozen_price']-$order_info['deposit_price']));
                }
            }
        }

        //发送短信通知消息
        if($ret_bill && $ret_order){
            M()->commit();
            //获取用户手机号
          
            return array(
                'status' => true,
                'balance' => $user_balance,
                'msg'  => '账单或订单状态已更改,账单后台自动扣款成功;',
                );
        }else{
            M()->rollback();
            return array(
                'status' => false,
                'msg'  => '账单或订单状态更改失败,账单后台自动扣款失败;',
                );
        }
    }
}