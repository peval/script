<?php

//设置上海市区
date_default_timezone_set('Asia/Shanghai');
spl_autoload_register(function($class){
	$path = str_replace('\\', '/',$class);
	$class_name = $path.'.class.php';
	include $class_name;
});

//设置错误处理
set_error_handler(function($errno,$errstr,$errfile,$errline,$errcontext){
	$path = '/script/log/';
	$file_prefix = 'gearman_worker_';
	$file_main = date('Ym');
	$file_suffix='.log';
	$file = $path.$file_prefix.$file_main.$file_suffix;
	if(!file_exists($file)){
		touch($file);
	}
	$error_message = '['.date('Y-m-d H:i:s').']'.' -- 错误号：'.$errno.' , 错误描述[ '.$errstr.' ] -- 错误位置：'."{$errfile} 第{$errline}行；\n";
	//错误信息日子记录
	file_put_contents($file, $error_message,FILE_APPEND);
},E_ALL);