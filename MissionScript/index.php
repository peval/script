<?php
use Application\Classes\Message;
use Application\Classes\Email;
require __DIR__.'/Application/init.php';

//加载gmworker
$gmworker= new GearmanWorker();
$gmworker->addServer('139.196.241.46','4730');
$gmworker->addFunction("kuaizu365_type_message", "message_func");
$gmworker->addFunction("kuaizu365_type_email", "email_func");

//开启运行
while($gmworker->work()){}

function message_func($job){
	$message = new Message();
	$data = $job->workload();
	$data = json_decode($data,true);
	$message -> request($data);
} 

function email_func($job){
	$email = new Email();
	$data = $job->workload();
	$data = json_decode($data,true);
	$email -> request($data);
}