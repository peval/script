<?php
namespace Common\Model;
use Think\Model;
class UserAccountsModel extends Model{
    //根据用户id获取，获取账户信息
    public function getByUserId($user_id){
        $map = array(
            'status'=>1,
            'user_id' =>$user_id,
            );
        $data = $this->where($map)->find();
        return $data;
    }


}