<?php
namespace Common\Model;
use Think\Model;
class OrderBillModel extends Model{
    public function getMonthByCurrentTime($time){
        $map = array(
            'start_time'=>array('elt',$time),
            'end_time'=>array('egt',$time),
            );
        $data = $this->where($map)->select();
        //echo $this->getLastSql();
        return $data;
    }

    //
    public function getNoPayById($id){
        $map = array(
           'id' =>$id,
           'status' => 0 
        );
        $data = $this->where($map)->find();
        return $data;
    }
    //获取当前时间的还款账单
    public function getCurrentBill($order_id){
        $map = array(
            'start_time'=>array('elt',time()),
            'end_time'=>array('gt',time()),
            'order_id' =>$order_id,
        );
        $data = $this->where($map)->find();
        return $data;
    }
    //通过用户id和时间信息获取账单信息
    public function getsByUserIdAndTime($user_id,$start_time,$end_time,$flag){
        $map = array(
            'user_id' =>$user_id,
            'end_time'=>array('between',array($start_time,$end_time)),
            );      
        $bills = array();
        if($flag){
            $bills = $this->where($map)->order('created_time desc')->select();
        }
        $map['status'] =0;
        $money = $this->where($map)->sum('rent_price');
        $map['status'] =1;
        $money_pay = $this->where($map)->sum('rent_price');
        return array('money'=>$money?$money:0,'money_pay'=>$money_pay?$money_pay:0,'bills'=>$bills);
    }
    public function getByIdAndUserId($id,$user_id){
        $condition = array(
            'id' => $id,
            'user_id'=>$user_id,
            'status' => 0,
        );
        $data = $this->where($condition)->find();
        return $data;
    }
    //通过账单trade_no 获取数据
    public function getByTradeNo($trade_no){
        //通过账单支付单号查询对应还款的记录
        $condition = array('trade_no'=>$trade_no,'status'=>0);
        $data = $this->where($condition)->find();
        return $data;
    }
    //通过order_id 获取全部未付款的账单
    public function getNoPayByOrderId($order_id){
        $condition = array('order_id'=>$order_id,'status'=>0);
        return $this->where($condition)->select();
    }
}