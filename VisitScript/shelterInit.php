<?php
include_once(__DIR__.'/Class/init.php');
include_once(__DIR__.'/Class/Statistics.class.php');
//抑制错误
try{
	$statis = new Statistics(60*10);
	if($statis->isForbidden()){
		exit('page not found');	
	}
	$statis -> start(18);
}catch(Exception $e){}
//变量清除
statisClear();
unset($statis);