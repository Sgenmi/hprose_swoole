<?php

define("BASE", __DIR__);
define("RPC_PATH", BASE . "/../Hprose");
require RPC_PATH . '/Hprose.php';

use Hprose\Http\Client as Hclient;
use Hprose\Swoole\Client as Sclient;

$server_config = [
    'daemonize' => 0,
    'max_request' => 5000000,
//    'open_cpu_affinity' => 1,
//    'task_worker_num' => 1,
    //'open_cpu_affinity' => 1,
    'task_worker_num' => 10,
    //'enable_port_reuse' => true,
    'worker_num' => 12,
    //'log_file' => __DIR__.'/swoole.log',
//    'reactor_num' => 24,
    'backlog' => 128,
    'dispatch_mode' => 1,
];

$test = new Hclient("http://127.0.0.1:8086", FALSE);
$serv = new Swoole\Http\Server("127.0.0.1", 9502);
$serv->set($server_config);
$serv->on("Request", function($request, $response) use( $serv) {
    
    //同步task
    $user_d = $serv->taskwait("tt");
    $response->end(json_encode($user_d));
});

$serv->on('task', function($serv, $taskId, $fromId, $data) use($test) {
    //如果user-get里要用到hello回来的值
    $helle_d = $test->hello("add");
    $user_d=$test->RPC_User_get("99999");
    echo $user_d."\n";
    return $helle_d;
});

$serv->on('finish', function($serv, $taskId, $data) {
    return $data;
});




$serv->start();
