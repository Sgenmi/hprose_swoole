<?php

define("BASE", __DIR__);
define("RPC_PATH", BASE . "/../Hprose");

require RPC_PATH . '/Hprose.php';

use \Hprose\Swoole\Client;

$test = new Client("http://127.0.0.1:8086");

//$test->setKeepAlive(FALSE);

$test->hello("9999", function($d) {
    echo "$d\n";
});

$test->RPC_User_get("heoole",function($a) {
    echo $a;
});

$test->reload(function($a) {
    echo $a;
});

$test->close();

