<?php
class dbConnection{
	protected static $redis = null;
	//获取redis 对象
	public static function getRedis(){
		if(self::$redis == null){
			$redis_config = include_once(__DIR__.'/../Conf/redis.php');
			$redis = new Redis();
			$redis->connect($redis_config['host'],$redis_config['port'],$redis_config['timeout']);
			$redis->auth($redis_config['passwd']);
			$redis->select($redis_config['db']);
			self::$redis = $redis;
			return $redis;
		}else{
			return self::$redis;
		}
	}

	public static function clearRedis(){
		self::$redis = null;	
	}

}
