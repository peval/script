<?php
namespace Common\Model;
use Think\Model;
class GoodsOrderModel extends Model{
    /*
    *通过id获取未支付的订单
    */
    public function getNoPayById($order_id){
        $map = array(
            'id' => $order_id,
            'status'  =>0,
        );
        $data = $this->where($map)->find();
        return $data;
    }
}