<?php

/**
 * Description of HttpServer
 *
 * @author Sgenmi
 * @Email 150560159@qq.com
 */

define("BASE", __DIR__);
define("RPC_PATH", BASE . "/../Hprose");
define("APP_PATH", BASE . "/../App");

require RPC_PATH . '/Hprose.php';

use Hprose\Swoole\Server;

function hello($name) {
    $a = HttpServer::$http->server->taskwait($name, 8);

    return json_encode($name);
}

function reload() {
    HttpServer::$http->server->reload();
    return "reload_ok";
}

function missFunction() {
    return "没有服务访求";
}

class HttpServer {

    public static $action_list = array();
    public static $instance;
    public static $http;
    public static $get;
    public static $header;
    public static $server;
    public static $db_config = [
        'server' => "127.0.0.1",
        'port' => 3306,
        'username' => 'root',
        'password' => '123456',
        'database_name' => 'magento',
        'database_type' => 'mysql',
        'prefix' => 'catalog_',
        'debug_mode' => false
    ];
    public $server_config = [
        'daemonize' => 0,
        'max_request' => 5000000,
//    'open_cpu_affinity' => 1,
//    'task_worker_num' => 1,
        //'open_cpu_affinity' => 1,
        'task_worker_num' => 10,
        //'enable_port_reuse' => true,
        'worker_num' => 10,
        //'log_file' => __DIR__.'/swoole.log',
//    'reactor_num' => 24,
        'backlog' => 128,
        'dispatch_mode' => 1,
    ];

    public function __construct() {

        $http = new Server("http://0.0.0.0:8086");
//        $http = new Server("http://0.0.0.0:8086",SWOOLE_PROCESS);
        $http->set($this->server_config);
        //$server->setGetEnabled(FALSE);
//
        $http->on("task", function($serv, $taskId, $fromId, $data) {

            $medoo = App\Core\Medoo::getInstance();
//            $data = $medoo->get('product_index_website', "*", array('website_id' => 1));
//          echo $medoo->last_query();
            print_r($data);
            return $data;
        });
        $http->on("finish", function($serv, $taskId, $data) {
            return $data;
        });

        $http->on("WorkerStart", function($serv, $worker_id) use ( $http) {
            HttpServer::load_file(APP_PATH);
            echo "启动成功\n";
            //动态，平滑发布新服务
            $action_list = HttpServer::$action_list;
            foreach ($action_list as $k => $v) {
                foreach ($v as $hv) {
                    if (isset($hv['class'])) { //和类相关发布
                        $http->$k($hv['func'], $hv['class'], $hv['alias'], $hv['option']);
                    } else if (isset($hv['alias'])) {   //和方法相关发布
                        $http->$k($hv['func'], $hv['alias'], $hv['option']);
                    } else {
                        $http->$k($hv['func'], $hv['option']);
                    }
                }
            }
        });

//        $http->setErrorTypes(E_ALL);
//        $http->setDebugEnabled();
//        $http->setCrossDomainEnabled();
//        $http->addFunction('hello');
        HttpServer::$http = $http;
        $http->start();
    }

    static function load_file($dir) {
        if (is_dir($dir)) {
            if ($file_h = opendir($dir)) {
                while (($file = readdir($file_h)) !== false) {
                    if ($file == '.' || $file == '..') {
                        continue;
                    }
                    $_file = $dir . "/" . $file;
                    if (is_file($_file) && strpos($_file, ".php") !== FALSE) {
                        require $_file;
                    } else if (is_dir($_file)) {
                        self:: load_file($_file);
                    }
                }
                closedir($file_h);
            }
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new HttpServer;
        }
        return self::$instance;
    }

}

HttpServer::getInstance();
