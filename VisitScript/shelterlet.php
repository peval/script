<?php
include_once(__DIR__.'/Class/init.php');
include_once(__DIR__.'/Class/Statistics.class.php');
$statis = new Statistics();
$statis -> let();
//变量清除
statisClear();
unset($statis);