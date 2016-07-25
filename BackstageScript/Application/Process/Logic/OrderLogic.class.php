<?php
namespace Common\Logic;
use Common\Service\SMSService;

class OrderLogic{
    //获取订单确认页面渲染数据入口
    public function orderInfo($user_info,$purchase){
        $info = array();
        $info['goods_info'] = D('Goods')->getById($purchase['goods_id']);
        if($info['goods_info']['price_type'] == 2){
            $info['price_info'] = D('GoodsPrice')->getByIdAndGoodsId($purchase['dynamic_price_id'],$info['goods_info']['id']);
        }
        if($info['goods_info']['price_type'] == 1){
            $info['price_info'] = array();
        }

        $info['user_account'] = D('UserAccounts')->getByUserId($user_info['id']);
        $info['user_address'] = D('UserAddress')->getsByUserId($user_info['id']);
        $info['rent_info']  = $this->rentInfo($purchase);

        $info['purchase']  = $purchase;
        if(empty($purchase['config'])){
            $items = array();
        }else{
            $items = $purchase['config'];
        }
        $info['config_info'] = D('GoodsConfigurationItems')->getsByGoodsAndIds($items,$purchase['goods_id']);
        // 机型配置
        $info['goods_type'] = $this->getByGoodID($purchase['goods_id']);
        $info['pay_info'] = $this->payInfo($info['goods_info'],$info['price_info'],$info['user_account'],$purchase,$info['config_info']);
        //获取优惠券 通过用户id 获取优惠券信息
        $info['coupons'] = $this->couponInfo($user_info['id'],$info['goods_info'],$info['pay_info']);
        return $info;
    }

    /**
     * 根据商品id查找机型配置
     * @param $good_id
     * @return array
     */
    public function getByGoodID($good_id){
        $detail_str = M("Goods")->where("id=$good_id")->getField("goods_type");
        if($detail_str == ''){
            return array();
        }
        $data = M("goods")->query("select gtd.detail_name,gt.type_name from
                          goods_type_detail gtd left join goods_type gt on gtd.type_id=gt.id where gtd.id in($detail_str)");
        return $data;
    }

    //购物车订单确认页面渲染数据入口
    public function cartInfo($user_info,$purchase){
        $info = array();
        //$info['goods_info'] = D('Goods')->getById($purchase['goods_id']);
        $info['user_account'] = D('UserAccounts')->getByUserId($user_info['id']);
        $info['user_address'] = D('UserAddress')->getsByUserId($user_info['id']);
        $info['coupons'] =D('VMoney')->getAvailableByUserId($user_info['id']);
        //$info['rent_info']  = $this->cartRentInfo($purchase);
        foreach($purchase as &$v){
            if(!empty($v['config_id'])){
                $v['configure'] = D('GoodsConfigurationItems')->getsByGoodsAndIds(explode(',',$v['config_id']),$v['goods_id']);
            }
        }
        $info['purchase']  = $purchase;
        /*if(empty($purchase['config'])){
            $items = array();
        }else{
            $items = $purchase['config'];
        }
        $info['config_info'] = D('GoodsConfigurationItems')->getsByGoodsAndIds($items,$purchase['goods_id']);*/
        $info['pay_info'] = $this->cartPayInfo($purchase);
        return $info;
    }


    //计算租赁信息
    public function rentInfo($purchase){
        $rent_info['rent_begin'] = date('Y-m-d',$purchase['rent_begin']);
        $rent_info['rent_periods'] = $purchase['rent_periods'];
        $rent_info['quantity'] = $purchase['quantity'];
        $rent_time_info = $purchase['rent_begin'];
        $rent_time_year = date('Y',$rent_time_info);
        $rent_time_month = date('n',$rent_time_info);
        $rent_time_day = date('j',$rent_time_info);
        $rent_end_month = $rent_time_month+$rent_info['rent_periods'];
        if($rent_end_month>12){
            $rent_end_year =$rent_time_year+1;
            $rent_end_month = $rent_end_month-12;
        }else{
            $rent_end_year=$rent_time_year;
        }

        $rent_end_day = date('t',strtotime("{$rent_end_year}-{$rent_end_month}"));
        if($rent_end_day>$rent_time_day){
            $rent_end_day = $rent_time_day;
        }
        $rent_info['rent_end'] = "{$rent_end_year}-{$rent_end_month}-{$rent_end_day}";
        return $rent_info;
    }

     //计算租赁结束信息
    public function rentEnd($rent){
        $rent_time_info = $rent['rent_begin'];
        $rent_time_year = date('Y',$rent_time_info);
        $rent_time_month = date('n',$rent_time_info);
        $rent_time_day = date('j',$rent_time_info);
        $rent_end_month = $rent_time_month+$rent['rent_periods'];
        if($rent_end_month>12){
            $rent_end_year =$rent_time_year+1;
            $rent_end_month = $rent_end_month-12;
        }else{
            $rent_end_year=$rent_time_year;
        }

        $rent_end_day = date('t',strtotime("{$rent_end_year}-{$rent_end_month}"));
        if($rent_end_day>$rent_time_day){
            $rent_end_day = $rent_time_day;
        }
        $rent_end = strtotime("{$rent_end_year}-{$rent_end_month}-{$rent_end_day}");
        return $rent_end;
    }

    /*
     * 计算支付的价格
     * 返回支付的金额的信息
    * array(
     *   'goods_price'=>商品的价格,
     *   'deposit_price'=>'保证金的价格',
     *   'is_use_credit'=>是否使用了免额度,
     *   'credit_price'=>是否使用了免额度,
     * )
    */
    public function payInfo($goods_info,$price_info,$user_account,$purchase,$config_info,$coupons=array()){
        $increase_price = 0;
        $info = array();
        foreach($config_info as $config){
            $increase_price+=$config['increase_price'];
        }
        if($goods_info['price_type'] == 1) {
            $info['goods_price'] = $goods_info['base_price'] + $increase_price;
        }
        if($goods_info['price_type'] == 2) {
            $info['goods_price'] = $price_info['price'] + $increase_price;
        }
        $info['rent_price'] = $info['goods_price'] * $purchase['quantity'];
        if(($purchase['quantity']*$goods_info['deposit_price'])>$user_account['credit_price']){
            $info['is_use_credit'] = 0;
            $info['credit_price'] = 0;
            $info['deposit_price'] = $purchase['quantity']*$goods_info['deposit_price'];
        }else{
            $info['is_use_credit'] = 1;
            $info['credit_price'] = $purchase['quantity']*$goods_info['deposit_price'];
            $info['deposit_price']=0;
        }
        $info['price']  = $info['deposit_price']+$info['rent_price'];
        //判断是否使用了优惠券
        if(empty($coupons)){
            return $info;
        }
        //使用了优惠券的处理逻辑
        $coupon_type = $coupons['coupon_type'];
        if($coupon_type == 1){
            $coupon_reach_price = $coupons['reach_price'];
            $coupon_price = $coupons['price'];
            if($coupon_reach_price < $info['price']){
                $info['price'] -= $coupon_price;
            }
        }
        if($coupon_type == 2){
            $coupon_price = $coupons['price'];
            if($coupon_price<$info['price']){
                $info['price'] -= $coupon_price;
            }
        }
        if($coupon_type == 3){
            $coupon_discount = $coupons['discount'];
            $info['price'] = $info['price'] * $coupon_discount/100;
        }
        return $info;
    }

    //购物车结算，计算中的价格
    public function cartPayInfo($purchase){
        $arr = array('deposit_price'=>0,'rent_price'=>0,'all_price');
        foreach($purchase as $v){
            $arr['deposit_price'] +=$v['deposit_price']*$v['quantity'];
            if(!empty($v['configure'])){
                foreach($v['configure'] as $item){
                    $arr['rent_price']+=$item['increase_price'];
                }
            }
            $arr['rent_price']+=$v['goods_price']*$v['quantity'];
        }
        $arr['all_price']=$arr['deposit_price']+$arr['rent_price'];
        return $arr;
    }

    //获取用户的优惠券信息
    public function couponInfo($user_id,$goods_info,$pay_info){
        $coupon = D('CouponCode')->getAvailableByUserId($user_id);
        foreach($coupon as &$v){
            $v = array_merge(D('Coupon')->getById($v['coupon_id']),$v);
            switch($v['used_restrict']){
                case 1:
                    $v['disabled'] = 'off';
                    break;
                case 2:
                    if($goods_info['is_new'] == 1){
                        $v['disabled'] = 'off';
                    }else{
                        $v['disabled'] = 'on';
                    }
                    break;
                case 3:
                    if($goods_info['is_new'] == 0){
                        $v['disabled'] = 'off';
                    }else{
                        $v['disabled'] = 'on';
                    }
                    break;
                case 4:
                    if($goods_info['is_easy_return'] == 1){
                        $v['disabled'] = 'off';
                    }else{
                        $v['disabled'] = 'on';
                    }
                    break;
                case 5:
                    if($goods_info['is_easy_return'] == 0){
                        $v['disabled'] = 'off';
                    }else{
                        $v['disabled'] = 'on';
                    }
                    break;
            }
            if($v['type'] == 1){
                if($pay_info['price']>$v['reach_price']){
                    $v['disabled'] = 'off';
                }else{
                    $v['disabled'] = 'on';
                }
            }
            if($v['type'] == 2){
                if($pay_info['price']>$v['price']){
                    $v['disabled'] = 'off';
                }else{
                    $v['disabled'] = 'on';
                }
            }
        }
        return $coupon;
    }

    //购物车订单生成入口
    public function createCart($user_info,$purchase){
        $_check = $this->_check($purchase);
        if(!$_check['status']){
            return $_check;
        }

        //获取收货地址
        $address_info = D('UserAddress')->getById($purchase['address_id'],$user_info['id']);
        if(empty($address_info)){
            return array('status'=>false,'msg'=>'用户收货地址不对');
        }
        $user_account = D('UserAccounts')->getByUserId($user_info['id']);
        if(empty($user_account)){
            return array('status'=>false,'msg'=>'用户账户不存在');
        }
      /*  $goods_info = D('Goods')->getById($purchase['goods_id']);
        if(empty($goods_info)){
            return array('status'=>false,'msg'=>'商品信息不存在');
        }*/
      /*  if(empty($purchase['config'])){
            $items = array();
        }else{
            $items = $purchase['config'];
        }
        $config_info = D('GoodsConfigurationItems')->getsByGoodsAndIds($items,$purchase['goods_id']);*/
        //获取租赁时间
        $rent_info = $this->rentInfo($purchase);
        //获取支付的金额信息
        //$pay_info = $info['pay_info'] = $this->payInfo($goods_info,$user_account,$purchase,$config_info);
        //余额支付的流程
      /*  if($purchase['pay_type']==2){
            $result = $this->offlineProcess($user_account,$pay_info,$address_info,$goods_info,$purchase,$rent_info,$config_info);
            if($result['status']){
                $result = array_merge($result,array('type'=>$purchase['pay_type']));
            }
            return $result;
        }*/
        //在线支付流程
       /* if($purchase['pay_type']==1){
            $result = $this->onlineProcess($user_account,$pay_info,$address_info,$goods_info,$purchase,$rent_info,$config_info);
            if($result['status']){
                $result = array_merge($result,array('type'=>$purchase['pay_type']));
            }
            return $result;
        }*/
    }

    //整理用户选取的优惠券信息
    public function couponSingleInfo($coupon_id,$user_id,$goods_info){
        $coupons =D('CouponCode')->getByIdAndUserId($coupon_id,$user_id);
        if(empty($coupons)){
            return array();
        }
        $coupons = array_merge(D('Coupon')->getById($coupons['coupon_id']),$coupons);
        switch($coupons['used_restrict']){
            case 1:
                $v['disabled'] = 'off';
                break;
            case 2:
                if($goods_info['is_new'] == 1){
                    $v['disabled'] = 'off';
                }else{
                    $v['disabled'] = 'on';
                }
                break;
            case 3:
                if($goods_info['is_new'] == 0){
                    $v['disabled'] = 'off';
                }else{
                    $v['disabled'] = 'on';
                }
                break;
            case 4:
                if($goods_info['is_easy_return'] == 1){
                    $v['disabled'] = 'off';
                }else{
                    $v['disabled'] = 'on';
                }
                break;
            case 5:
                if($goods_info['is_easy_return'] == 0){
                    $v['disabled'] = 'off';
                }else{
                    $v['disabled'] = 'on';
                }
                break;
        }
        return $coupons;
    }

    //订单生成入口
    public function create($user_info,$purchase){

        $_check = $this->_check($purchase);
        if(!$_check['status']){
            return $_check;
        }

        //获取收货地址
        $address_info = D('UserAddress')->getById($purchase['address_id'],$user_info['id']);
        if(empty($address_info)){
            return array('status'=>false,'msg'=>'用户收货地址不对');
        }
        $user_account = D('UserAccounts')->getByUserId($user_info['id']);
        if(empty($user_account)){
            return array('status'=>false,'msg'=>'用户账户不存在');
        }
        $goods_info = D('Goods')->getById($purchase['goods_id']);
        if(empty($goods_info)){
            return array('status'=>false,'msg'=>'商品信息不存在');
        }
        //获取用户的选择属性信息
        $select_info = array();
        if(!empty($goods_info['goods_type'])){
            $goods_type = $goods_info['goods_type'];
            $goods_type = explode(',',$goods_type);
            $select_info = D('GoodsTypeDetail')->getByids($goods_type);
        }
        if($goods_info['price_type'] == 2){
            $price_info = D('GoodsPrice')->getByIdAndGoodsId($purchase['dynamic_price_id'],$goods_info['id']);
        }
        if($goods_info['price_type'] == 1){
            $price_info = array();
        }
        if(empty($purchase['config'])){
            $items = array();
        }else{
            $items = $purchase['config'];
        }

        //优惠券
        if(empty($purchase['coupon'])){
            $coupons = array();
            $purchase['use_coupons'] = 0;
            $purchase['coupons_id'] =0;
        }else{
            //$coupons =D('CouponCode')->getByIdAndUserId($purchase['coupon'],$user_info['id']);
            $coupons = $this->couponSingleInfo($purchase['coupon'],$user_info['id'],$goods_info);
            if(empty($coupons)) {
                return array('status'=>false,'msg'=>'优惠券信息不存在');
                $purchase['use_coupons'] = 0;
                $purchase['coupons_id'] = 0;
            }
            if($coupons['disabled'] == 'on'){
                return array('status'=>false,'msg'=>'选择的优惠券,不可用');
            }

            $purchase['use_coupons'] = 1;
            $purchase['coupons_id'] = $coupons['id'];
        }

        $config_info = D('GoodsConfigurationItems')->getsByGoodsAndIds($items,$purchase['goods_id']);

        //获取租赁时间
        $rent_info = $this->rentInfo($purchase);
        //获取支付的金额信息
        $pay_info = $info['pay_info'] = $this->payInfo($goods_info,$price_info,$user_account,$purchase,$config_info,$coupons);

        //线下支付
        if($purchase['pay_type']==4){
            $result = $this->lineProcess($user_account,$pay_info,$address_info,$goods_info,$price_info,$purchase,$rent_info,$config_info,$select_info);
            if($result['status']){
                $result = array_merge($result,array('type'=>$purchase['pay_type']));
            }
            return $result;
        }

        //余额支付的流程
        if($purchase['pay_type']==2){
            $result = $this->offlineProcess($user_account,$pay_info,$address_info,$goods_info,$price_info,$purchase,$rent_info,$config_info,$select_info);
            if($result['status']){
                $result = array_merge($result,array('type'=>$purchase['pay_type']));
            }
            return $result;
        }
        //在线支付流程
        if($purchase['pay_type']==1){
            $result = $this->onlineProcess($user_account,$pay_info,$address_info,$goods_info,$price_info,$purchase,$rent_info,$config_info,$select_info);
            if($result['status']){
                $result = array_merge($result,array('type'=>$purchase['pay_type']));
            }
            return $result;
        }
       
    }
    //数据验证
    public function _check($purchase){
        //订单配置信息验证
        if(mb_strlen($purchase['remark'],'UTF-8')>20){
            return array('status'=>false,'msg'=>'备注200字以内');
        }
        if(!is_numeric($purchase['address_id'])&&$purchase['address_id']<1){
            return array('status'=>false,'msg'=>'收货地址不对');
        }
        if(!in_array($purchase['pay_type'],array(1,2,3,4,5,6))){
            return array('status'=>false,'msg'=>'付款方式不对');
        }
        return array('status'=>true,'msg'=>'');
    }

    /**
     * 用户线下支付订单数据入库流程
     * $purchase 购物的信息
     * $user_account 用户账户信息
     * $pay_info 订单的金额信息
     * $address_info 用户账户信息
     * $goods_info 商品信息
     */
    protected function lineProcess($user_account,$pay_info,$address_info,$goods_info,$price_info,$purchase,$rent_info,$config_info,$select_info){
        //开启事务
        M()->startTrans();
        //订单商品入库
        $order= $this->orderAdd($pay_info,$user_account,$goods_info,$price_info,$purchase,$rent_info,array('status'=>0,'pay_time'=>0));
        if(empty($order)){
            M()->rollback();
            return array('status'=>false,'msg'=>'订单生成失败');
        }
        //订单信息处理
        $order_info_id=$this->orderInfoAdd($order['id'],$address_info,$goods_info,$purchase,$config_info);
        if(empty($order_info_id)){
            M()->rollback();
            return array('status'=>false,'msg'=>'订单信息生成失败');
        }
        //订单商品添加
        $order_commodity = $this->orderGoodsAdd($order,$pay_info,$goods_info,$price_info,$config_info,$select_info,$purchase);
        if(empty($order_commodity)){
            M()->rollback();
            return array('status'=>false,'msg'=>'订单商品添加失败');
        }
        //免押金逻辑处理
        $ret = $this->creditChange($user_account,$pay_info);
        if(!$ret){
            M()->rollback();
            return array('status'=>false,'msg'=>'免押金逻辑处理失败');
        }

        //优惠券逻辑处理
        $change_coupons = $this->useCoupons($purchase,$pay_info);
        if(!$change_coupons){
            M()->rollback();
            return array('status'=>false,'msg'=>'优惠券使用失败');
        }

        //逻辑处理完毕 提交数据
        M()->commit();
        return array('status'=>true,'msg'=>'订单成功，请尽快付款');
    }


    /**
    * 参数信息
    * $purchase 购物的信息
    * $user_account 用户账户信息
    * $pay_info 订单的金额信息
    * $address_info 用户账户信息
    * $goods_info 商品信息
    */
    protected function offlineProcess($user_account,$pay_info,$address_info,$goods_info,$price_info,$purchase,$rent_info,$config_info,$select_info){
        if($user_account['balance']<$pay_info['price']){
            return array('status'=>false,'msg'=>'余额不足');
        }
        //开启事务
        M()->startTrans();
        //订单商品入库
        $order= $this->orderAdd($pay_info,$user_account,$goods_info,$price_info,$purchase,$rent_info);
        if(empty($order)){
            M()->rollback();
            return array('status'=>false,'msg'=>'订单生成失败');
        }
        //订单信息处理
        $order_info_id=$this->orderInfoAdd($order['id'],$address_info,$goods_info,$purchase,$config_info);
        if(empty($order_info_id)){
            M()->rollback();
            return array('status'=>false,'msg'=>'订单信息生成失败');
        }
        //订单商品添加
        $order_commodity = $this->orderGoodsAdd($order,$pay_info,$goods_info,$price_info,$config_info,$select_info,$purchase);
        if(empty($order_commodity)){
            M()->rollback();
            return array('status'=>false,'msg'=>'订单商品添加失败');
        }
        //免押金逻辑处理
        $ret = $this->creditChange($user_account,$pay_info);
        if(!$ret){
            M()->rollback();
            return array('status'=>false,'msg'=>'免押金逻辑处理失败');
        }
        //购买金额变动
        $ret = $this->priceChange($pay_info,$user_account,$goods_info,$purchase,$order);
        if(!$ret){
            M()->rollback();
            return array('status'=>false,'msg'=>'购买金额处理失败');
        }

        //优惠券逻辑处理
        $change_coupons = $this->useCoupons($purchase,$pay_info);
        if(!$change_coupons){
            M()->rollback();
            return array('status'=>false,'msg'=>'优惠券使用失败');
        }
        //生成支付记录

        $this->payLog($order,$purchase);

        //账单生成
        //$ret = $this->createOrderBill($order);
        if(!$ret){
            M()->rollback();
            return array('status'=>false,'msg'=>'账单生成失败失败');
        }
        //逻辑处理完毕 提交数据
        M()->commit();
        return array('status'=>true,'msg'=>'订单已完成，购买成功');

    }  

      /**
    * 用户在线支付订单数据入库流程
    * $purchase 购物的信息
    * $user_account 用户账户信息
    * $pay_info 订单的金额信息
    * $address_info 用户账户信息
    * $goods_info 商品信息
    */
    protected function onlineProcess($user_account,$pay_info,$address_info,$goods_info,$price_info,$purchase,$rent_info,$config_info,$select_info){
        //开启事务
        M()->startTrans();
        //订单商品入库
        $order= $this->orderAdd($pay_info,$user_account,$goods_info,$price_info,$purchase,$rent_info,array('status'=>0,'pay_time'=>0));
        if(empty($order)){
            M()->rollback();
            return array('status'=>false,'msg'=>'订单生成失败');
        }
        //订单信息处理
        $order_info_id=$this->orderInfoAdd($order['id'],$address_info,$goods_info,$purchase,$config_info);
        if(empty($order_info_id)){
            M()->rollback();
            return array('status'=>false,'msg'=>'订单信息生成失败');
        }
        //订单商品添加
        $order_commodity = $this->orderGoodsAdd($order,$pay_info,$goods_info,$price_info,$config_info,$select_info,$purchase);
        if(empty($order_commodity)){
            M()->rollback();
            return array('status'=>false,'msg'=>'订单商品添加失败');
        }
        //免押金逻辑处理
        $ret = $this->creditChange($user_account,$pay_info);
        if(!$ret){
            M()->rollback();
            return array('status'=>false,'msg'=>'免押金逻辑处理失败');
        }

        //优惠券逻辑处理
        $change_coupons = $this->useCoupons($purchase,$pay_info);
        if(!$change_coupons){
            M()->rollback();
            return array('status'=>false,'msg'=>'优惠券使用失败');
        }
        //生成支付记录
        $this->payStep($order);
        //逻辑处理完毕 提交数据
        M()->commit();
        return array('status'=>true,'msg'=>'订单生成成功，去支付..','url'=>U('Process/orderShow',array('order_id'=>$order['id'])));

    }  

    //订单数据入库入库
    protected function orderAdd($pay_info,$user_account,$goods_info,$price_info,$purchase,$rent_info,$params=array()){
        $order = array(
            'inner_trade_no' => create_order_no(),
            'user_id' =>$user_account['user_id'],
            'rent_price' => $pay_info['rent_price'],
            'is_use_credit' => $pay_info['is_use_credit'],
            'deposit_price' => $pay_info['is_use_credit']?$pay_info['credit_price']:$pay_info['deposit_price'],
            'order_price' => $pay_info['price'],
            'rent_begin' =>$purchase['rent_begin'],
            'rent_end' =>strtotime($rent_info['rent_end']),
            'rent_periods' =>$purchase['rent_periods'],
            'quantity' =>$purchase['quantity'],
            'goods_id' =>$goods_info['id'],
            'is_easy_return' =>$goods_info['is_easy_return'],
            'is_return' => $goods_info['price_type']==2?$price_info['is_return']:1,
            'pay_type' =>$purchase['pay_type'],
            'pay_time' =>time(),
            'status' =>1,
            'created_time' =>time(),
            );
        $data = array_merge($order,$params);
        $order_id = M('GoodsOrder')->add($data);
        if(empty($order_id)){
            return array();
        }else{
            $order['id']=$order_id;
            return $order;
        }
    }
    //订单详情信息入库
    protected function orderInfoAdd($order_id,$address_info,$goods_info,$purchase,$config_info){
        //处理商品的配置的信息
        $order_info = array(
            'order_id' =>$order_id,
            'received_tel' =>$address_info['tel'],
            'received_name' =>$address_info['name'],
            'received_province' =>$address_info['province'],
            'received_city' =>$address_info['city'],
            'received_region' =>$address_info['region'],
            'received_address' =>$address_info['detailed_address'],
           /* 'goods_name' =>$goods_info['goods_name'],
            'img_url' =>$goods_info['location'].$goods_info['path'].'/'.$goods_info['file_name'],
            'configure' =>json_encode($config_info,JSON_UNESCAPED_UNICODE),*/
            'remark' =>$purchase['remark'],
            'use_coupons' =>$purchase['use_coupons'],
            'coupons_id' =>$purchase['use_coupons']==1?$purchase['coupons_id']:0,
            /*'use_coupons' =>0,
            'counpons_id' =>0,*/
            'created_time' =>time(),
            ); 
        $order_info_id = M('GoodsOrderInfo')->add($order_info);
        return $order_info_id;
    }
    //订单商品添加
    public function orderGoodsAdd($order,$pay_info,$goods_info,$price_info,$config_info,$select_info,$purchase){
        $data = array(
            'order_id' =>$order['id'],
            'user_id' =>$order['user_id'],
            'goods_id'=>$goods_info['id'],
            'quantity'=>$purchase['quantity'],
            'status' =>1,
            'goods_price' =>$pay_info['goods_price'],
            'deposit_price' =>$goods_info['deposit_price'],
            'goods_name' =>$goods_info['goods_name'],
            'img_url' =>$goods_info['location'].$goods_info['path'].'/'.$goods_info['file_name'],
            'configure' =>json_encode($config_info,JSON_UNESCAPED_UNICODE),
            'parameter' =>json_encode($select_info,JSON_UNESCAPED_UNICODE),
            'price_type' => $goods_info['price_type'],
            'price_id' =>$goods_info['price_type']==2?$price_info['id']:0,
            'is_return' =>$goods_info['price_type']==2?$price_info['is_return']:1,
            'price_info' =>json_encode($price_info,JSON_UNESCAPED_UNICODE),
            'created_time'=>time(),
            );
        return M('OrderCommodity')->add($data);
    }
    //线下支付金额处理
    protected function priceChange($pay_info,$user_account,$goods_info,$purchase,$order){
        $remain_price = $user_account['balance']-$pay_info['price'];
        $data = array('balance'=>$remain_price);
        if($pay_info['is_use_credit']==0){
            $data['frozen_balance'] = $user_account['frozen_balance']+$pay_info['deposit_price'];
        }
        $ret = M('UserAccounts')->where('id='.$user_account['id'])->save($data);
        if($ret){
            //余额资金变动成功则写入一天资金变动记录
            $data = array(
                'user_id' =>$user_account['user_id'],
                'type' =>1,
                'des' =>'用户余额购买商品',
                'trade_des' =>'用户余额购买商品',
                'price'=>$pay_info['price'],
                'goods_id' =>$goods_info['id'],
                'order_id' =>$order['id'],
                'inner_trade_no' =>$order['inner_trade_no'],
                'outer_trade_no' =>'',
                'created_time' =>time(),
                );
            M('MoneyFlow')->add($data);
            return true;
        }else{
            return false;
        }
    }

    //使用了免押金额度逻辑处理
    public function creditChange($user_account,$pay_info){
        if(!$pay_info['is_use_credit']){
            return true;
        }
        $credit_price = $pay_info['credit_price'];
        $data = array(
            'credit_price' => $user_account['credit_price'] -$pay_info['credit_price'],
            'frozen_price' => $user_account['frozen_price'] + $pay_info['credit_price'],
            );
        $ret = M('UserAccounts')->where('id='.$user_account['id'])->save($data);
        return $ret;
    }

    //优惠券使用
    public function useCoupons($purchase){
        if($purchase['use_coupons']==0){
            return true;
        }
        if($purchase['use_coupons']==1){
            $ret = M('CouponCode')->where('id='.$purchase['coupons_id'])->save(array('status'=>2,'used_time'=>time()));
            if($ret){
                return true;
            }else{
                return false;
            }
        }
    }

    //金额变动记录
    public function payLog($order,$purchase){
        $data = array(
            'inner_trade_no' => $order['inner_trade_no'],
            'user_id' =>$order['user_id'],
            'goods_id' =>$order['goods_id'],
            'subject' =>'用户余额购买产品',
            'info' =>json_encode($purchase,JSON_UNESCAPED_UNICODE),
            'step' =>3,
            'created_time' =>time()
            );
        $ret = M('OrderPayInfo')->add($data);
        return $ret;
    }
    //订单交易记录记录线上交易流程记录
    public function payStep($order,$params=array()){
        $data = array(
            'inner_trade_no' => $order['inner_trade_no'],
            'user_id' =>$order['user_id'],
            //'goods_id' =>$order['goods_id'],
            'subject' =>'订单初始化完成，待用户支付',
            'info' =>json_encode($order,JSON_UNESCAPED_UNICODE),
            'step' =>1,
            'created_time' =>time()
        );
        $data = array_merge($data,$params);
        $ret = M('OrderPayInfo')->add($data);
        return $ret;
    }

     //订单交易记录记录线上交易流程记录,已丛谢
    public function payStep_del($order,$params=array()){
        $data = array(
            'inner_trade_no' => $order['inner_trade_no'],
            'user_id' =>$order['user_id'],
            'goods_id' =>$order['goods_id'],
            'subject' =>'订单初始化完成，待用户支付',
            'info' =>json_encode($order,JSON_UNESCAPED_UNICODE),
            'step' =>1,
            'created_time' =>time()
            );
        $data = array_merge($data,$params);
        $ret = M('GoodsPayInfo')->add($data);
        return $ret;
    }
   /*
   *生成订单的账单
   * $order 订单信息
   */
    public function createOrderBill($order){
        //账单基本信息
        $data = array(
            'rent_price'=>$order['rent_price'],
            'rent_periods'=>$order['rent_periods'],
            'order_trade_no' =>$order['inner_trade_no'],
            'order_id'=>$order['id'],
            'user_id'=>$order['user_id'],
            'quantity' =>$order['quantity'],
            );
        $begin_time = $order['rent_begin'];
        //生成账单信息
        for($i=1;$i<=$order['rent_periods'];$i++){
            $data['bill_no'] = create_bill_no();
            $data['created_time'] = time();
            $data['sequence'] = $i;
            if($i==1){
                $data['status'] = 1;
            }else{
                $data['status'] = 0;
            }
            $data['start_time'] = $begin_time;
            $end_time = $this->calculateBillTime($i,$begin_time);
            $data['end_time'] = $end_time;
            $begin_time= $end_time;
            $ret = M('OrderBill')->add($data);
            if(!$ret){
                return false;
            }
        }
        //账单全部入库，返回true
        return true;
    }


    //计算账单时间
    public function calculateBillTime($sequence,$begin_time){
        $year = date('Y',$begin_time);
        $month =date('n',$begin_time);
        $day = date('j',$begin_time);
        $end_month=$month+1;//$sequence;
        if($end_month>12){
            $end_year=$year+1;
            $end_month = $end_month-12;
        }else{
            $end_year=$year;
        }

        $end_day = date('t',strtotime("{$end_year}-{$end_month}"));
        if($end_day>$day){
            $end_day = $day;
        }
        $end_time = strtotime("{$end_year}-{$end_month}-{$end_day}");
        return $end_time;
    }

    /**
    * 用户购买支付回调处理，订单数据处理更改
    */
    public function payCallback($params){
        $order_info = D('GoodsOrder')->getByTradeNo($params['out_trade_no']); 
        if(empty($order_info)){
           $data = array(
                'inner_trade_no' =>$params['out_trade_no'],
                'description'  => '支付回调验证成功,订单查找不存在',
                'order_id' =>$order_info['id'],
                'info' =>json_decode($params),
                'order_info' =>json_decode($order_info),
                'created_time' =>date('Y-m-d H:i:s')
                );
            M('TradePayLog')->add($data);
            return false;
        }
        if($order_info['order_price']!=$params['total_fee']){
            //记录错误日志
            $data = array(
                'inner_trade_no' =>$params['out_trade_no'],
                'description'  => '商品购买，支付回调，金额验证不匹配',
                'order_id' =>$order_info['id'],
                'info' =>json_decode($params),
                'order_info' =>json_decode($order_info),
                'created_time' =>date('Y-m-d H:i:s')
                );
            M('TradePayLog')->add($data);
            return false;
        }
        //订单存在，数据验证成功,开始事务处理
        M()->startTrans();
        if(!$this->changeStatus($order_info,$params)){
            M()->rollback();
            return false;
        }
        //记录购买的流程步骤
        $info =array(
            'info'=>json_encode($params,JSON_UNESCAPED_UNICODE),
            'subject' =>'支付回调成功，订单状态改为已支付状态',
            'step' =>3,
            'created_time' =>time()
            );
        $this->payStep($order_info,$info);
        //支付回调生成账单，用户点击确认收货生成账单
       /* if(!$this->createOrderBill($order_info)){
            M()->rollback();
            return false;
        }*/
        M()->commit();

        return true;

    }

    //支付回调更改订单状态
    public function changeStatus($order_info,$params){
        $data= array(
            'status' =>1,
            'pay_time' =>time(),
            'outer_trade_no' =>$params['trade_no'],
            );
        $ret = M('GoodsOrder')
            ->where('id='.$order_info['id'])
            ->save($data);
        return $ret;
    }
}