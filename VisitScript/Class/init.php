<?php
//$redis_config = include_once(__DIR__.'/../Conf/redis.php');
include_once(__DIR__.'/dbConnection.class.php');

function conn(){
	$redis = dbConnection::getRedis(); 
	$db = null;
	return $redis;
}

function statisClear(){
	dbConnection::clearRedis(); 
}
