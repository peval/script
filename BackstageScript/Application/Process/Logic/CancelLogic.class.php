<?php
/*
* 后台数据逻辑处理
*/
namespace Process\Logic;
class CancelLogic{
    protected $db_process_nums = 20;
    //订单取消操作
    public function cancel($cancel,$user){
        $this->_nopayData(); 
        $this->_nopayProcess();
    }

    //缓存未支付的订单数据
    public function _nopayData(){
        $where = array('created_time'=>array('lt',time()-3600*24),'status' => 0);
        $nums = M('GoodsOrder')->where($where)->count();
        $loop = ceil($nums/$this->db_process_nums);
        //循环插入数据
        for($i =0 ; $i<$loop ; $i++){
            $limit = $i*$this->db_process_nums.','.$this->db_process_nums;

            $nopay_data = M('GoodsOrder')->where($where)->limit($limit)->select();
            $insert_data = array();
            //获取拼接的数据
            foreach($nopay_data as $data){
                $single_data = array(
                    'order_info' => json_encode($data,JSON_UNESCAPED_UNICODE),
                    'status' => 1,
                    'created_time' => time(),
                    );
                $insert_data[] = $single_data; 
            }
            //bill buffer 数据入库
            $is = M('NopayOrderCancelData')->addAll($insert_data); 
        }
    }

    //未支付的订单数据处理 
    public function _nopayProcess(){
        $where = array('status'=>1);
        $nums = M('NopayOrderCancelData')->where($where)->count();
        $loop = ceil($nums/$this->db_process_nums);
        for($i=0;$i<$loop;$i++){
            $limit = $i*$this->db_process_nums.','.$this->db_process_nums;
            $buffer_data = M('NopayOrderCancelData')->where($where)->limit($limit)->select();
            foreach($buffer_data as $data){
                $order_info = json_decode($data['order_info'],true);
                $data['status'] = 2;
                $ret = $this->operate($order_info);
                $data['cancel_result'] = json_encode($ret,JSON_UNESCAPED_UNICODE);
                $data['cancel_time'] = time();
                M('NopayOrderCancelData') ->save($data);
            }
        }
    }
    //订单取消操作
    public function operate($order){
        $order = D('GoodsOrder')->getNoPayById($order['id']);
        if(empty($order) || $order['status']!=0){
            return array('status'=>false,'msg'=>'订单实时查询不存在');
        }
        //开启事务，订单取消操作
        M()->startTrans();
        //判断是否回退免押金额度
        if($order['is_use_credit']==1){
            $credit_price = $order['deposit_price'];
            $user_account = D('UserAccounts')->getByUserId($order['user_id']);
            if(empty($user_account) || $user_account['frozen_price']<$credit_price){
                M()->rollback();
                return array('status'=>false,'msg'=>'数据有误');
            }
            $account_update = array(
                'credit_price' => $user_account['credit_price']+$credit_price,
                'frozen_price' => $user_account['frozen_price']-$credit_price,
            );
            //更新账户信息
            $ret = M('UserAccounts')->where('id='.$user_account['id'])->save($account_update);
            if(!$ret){
                M()->rollback();
                return array('status'=>false,'msg'=>'押金额度退回失败');
            }
        }
        //取消订单数据更新
        $order_info_update = array('reason'=>'后台未支付订单自动取消');
        $ret1 = M('GoodsOrderInfo')->where('order_id='.$order['id'])->save($order_info_update);

        //取消的订单状态更新
        $order_update = array(
            'status'=>10,
        );
        $ret2 = M('GoodsOrder')->where('id='.$order['id'])->save($order_update);
        if($ret2){
            M()->commit();
            return array('status'=>true,'msg'=>'订单取消成功');
        }else{
            M()->rollback();
            return array('status'=>false,'msg'=>'订单取消失败');
        }
    }

    //判断是否已经提交过了
    public function cancelIsset($order_id,$user_id){
        $map = array(
            'order_id' =>$order_id,
            'user_id' =>$user_id,
        );
        $data = M('OrderCancelLog')->where($map)->find();
        if(empty($data)){
            return false;
        }else{
            return $data;
        }

    }


    //未发货之前取消订单
    public function orderCancel($info,$user){
        $order_id = $info['order_id'];
        $user_id = $user['id'];
        $data=$this->_checkOrder($order_id,$user_id,1);
        if(!$data){
            return array('status'=>false,'msg'=>'订单不存在');
        }

        //判断此订单是否已经提交过
        if($this->_cancelCheck($order_id,$user_id,$data['inner_trade_no'])['status']==1){
            return array('status'=>false,'msg'=>'该订单已提交过取消申请，客服正在审核，请您耐心等待');

        }

        if($this->_cancelCheck($order_id,$user_id,$data['inner_trade_no'])['status']==2){
            return array('status'=>false,'msg'=>'该订单已申请过取消，审核未通过,如有疑问请联系客服！');

        }

        //调用取消操作
        return $this->orderOperate($info,$data);
    }

    public function _checkOrder($order_id,$user_id,$status){
            $map = array(
                'status' => $status ,
                'id' =>$order_id,
                'user_id' =>$user_id,
            );
            $data = M('GoodsOrder')->where($map)->find();
            if(empty($data)){
                return false;
            }else{
                return $data;
            }
    }

    //用户申请 订单取消操作
    public function orderOperate($info,$order){
            //开启事务，订单取消操作
            M()->startTrans();
            //判断是否回退免押金额度
            $user_account = D('UserAccounts')->getByUserId($order['user_id']);
            if($order['is_use_credit']==1){
                if(empty($user_account) || $user_account['frozen_price']<$order['deposit_price']){
                    M()->rollback();
                    return array('status'=>false,'msg'=>'数据有误，支付的押金与订单中的押金不符');
                }
            }
            else{
                if($user_account['frozen_balance']<$order['deposit_price']){
                    M()->rollback();
                    return array('status'=>false,'msg'=>'数据有误，支付的押金与订单中的押金不符');
                }
             }

            $data=array(
                "user_id"=>$order['user_id'],
                "orde_id"=>$order['id'],
                "inner_trade_no"=>$order['inner_trade_no'],
                "is_use_credit"=>$order['is_use_credit'],
                "order_price"=>$order['deposit_price'],
                "deposit_price"=>$order['deposit_price'],
                "cancel_type"=>1,
                "reason"=>$info['reason'],
                "status"=>1,
                "created_time"=>time()
            );
            $ret=  M("OrderCancelLog")->add($data);
            if($ret){
                M()->commit();
                return array('status'=>true,'msg'=>'申请订单取消成功');
            }else{
                M()->rollback();
                return array('status'=>false,'msg'=>'申请订单取消失败');
            }
    }

    //验证订单是否已经提交
    public function _cancelCheck($order_id,$user_id,$inner_trade_no){
        $map = array(
            'inner_trade_no' => $inner_trade_no ,
            'order_id' =>$order_id,
            'user_id' =>$user_id,
        );
        $data = M('OrderCancelLog')->where($map)->find();
        if(empty($data)){
            return false;
        }else{
            return $data;
        }
    }


}