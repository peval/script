<?php
namespace Home\Controller;
use Think\Controller;
class IndexController extends Controller {
    public function index(){
    	echo date('Y-m-d H:i:s');
    	exit;
    	$data = M('GoodsOrder')->find();
    	print_r($data);
    }
}