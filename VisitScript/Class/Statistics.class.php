<?php
class Statistics{
	public $ip;
	protected $redis;
	protected $forbidden_time;
	protected $alive_time = 86400;
	public function __construct($alive_time=0){
		$this->forbidden_time = $alive_time?$alive_time:$this->alive_time;
		$this->ip = $_SERVER['REMOTE_ADDR'];
		if(!$this->ip){
			exit('forbidden client request !');
		}
		$this->redis = conn();
	}
	public function start($per_second=10){
		if(!$this->ip){
			return false;
		}
		$this->redis->select(9);
		$flag = $this->redis->exists($this->ip);	
		if(!$flag){
			$this->redis->psetex($this->ip,1000,1);
		}else{
			if(!$this->isLegal($per_second)){
				return false;
			}else{
				$this->redis->incr($this->ip);
				return true;
			}
		}
	}

	//检查用户的请求是否合法
	public function isLegal($per_second){
		$this->redis->select(9);
		$num = $this->redis->get($this->ip);
		$remaind_time = $this->redis->pttl($this->ip);
		if($num>$per_second && $remaind_time>=0){
			$this->_setForbidden();
			return false;
		}else{
			return true;
		}
	}
	//检查ip是否在禁用的表单中
	public function isForbidden(){
		$this->redis->select(10);
		//return $this->redis->exists($this->ip);
		$is_alive = $this->redis->pttl($this->ip);
		if($is_alive==false){
			return false;
		}else{
			if($is_alive>0){
				return true;
			}else{
				return false;
			}	
		}
	}

	public function _setForbidden(){
		$this->redis->select(10);
		$this->redis->setex($this->ip,$this->forbidden_time,'is_forbidden');
	}

	public function let(){
		$this->redis->select(10);
		$this->redis->delete($this->ip);
		$this->redis->select(10);
		$this->redis->delete($this->ip);
	}
}