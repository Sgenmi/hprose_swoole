<?php

/**
 * Description of HttpServer
 *
 * @author Sgenmi
 * @Email 150560159@qq.com
 */
define("APP_VERSION", "1.0.0");
define("HPROSE_VERSION", "2.0.30");
define("HPROSE_SWOOLE", "2.0.11");
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

    private $rpc_type = "http";
    private $rpc_server = "127.0.0.1";
    private $rpc_name = "/user";
    private $rpc_port = "8086";
    private $zookeeper_sers = "192.168.1.134:2181,192.168.1.244:2182,192.168.1.244:2183";
    public static $db_config = [];
    public $server_config = [
        'daemonize' => 0,
        'max_request' => 5000000,
//    'open_cpu_affinity' => 1,
//    'task_worker_num' => 1,
        //'open_cpu_affinity' => 1,
        'task_worker_num' => 1,
        //'enable_port_reuse' => true,
        'worker_num' => 1,
        //'log_file' => __DIR__.'/swoole.log',
//    'reactor_num' => 24,
        'backlog' => 128,
        'dispatch_mode' => 1,
    ];
    public static $action_list = [];
    public static $instance;
    public static $http;
    public static $get;
    public static $header;
    public static $server;
    //zookeeper配置

    private $zookeeper = null;
    private $zookeeper_conn_num = 0;
    const CONNECT_COUNT_RESET = 3;

    public function __construct() {

        $serv_add = $this->rpc_type . "://" . $this->rpc_server . ":" . $this->rpc_port;
        $http = new Server($serv_add);
        $http->set($this->server_config);
        //$server->setGetEnabled(FALSE);

        $http->on('start', function($serv) {
            echo "开始服务\n";
            //增加zookeeper服务分支
            $this->publist_zookeeper('create');
        });
        $http->on('Shutdown', function() {
            echo "服务停止\n";
            //删除zookeeper服务分支
            $this->publist_zookeeper('delete');
        });

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
            require APP_PATH . "/AppLoad.php";
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

    private function publist_zookeeper($action = 'create') {
        $this->get_zookeeper();
        $params = array(
            array(
                'perms' => \Zookeeper::PERM_ALL,
                'scheme' => 'world',
                'id' => 'anyone',
            )
        );
        $json = array(
            'weight' => 30,
            'version' => APP_VERSION,
            'status' => 1,
            'time' => time()
        );
        !$this->zookeeper->exists($this->rpc_name) && $this->zookeeper->create($this->rpc_name, null, $params);
        $zooNode = $this->rpc_name . "/" . $this->rpc_server . ":" . $this->rpc_port;

        if ($this->zookeeper->exists($zooNode)) {
            $this->zookeeper->delete($zooNode);
        }

        if ($action == 'create') {
            $this->zookeeper->create($zooNode, json_encode($json), $params, \Zookeeper::EPHEMERAL);
        }
    }

    private function get_zookeeper() {
        try {
            $this->zookeeper = new \Zookeeper();
            $this->zookeeper->connect($this->zookeeper_sers, NULL, 100);
            $this->zookeeper->exists("/");
        } catch (\ZookeeperConnectionException $ex) {
            $this->zookeeper_conn_num++;
            if ($this->zookeeper_conn_num >= self:: CONNECT_COUNT_RESET) {
                exit("连接zookeeper出错\n");
            }
            $this->get_zookeeper();
        } catch (\ZookeeperException $ec) {
            $this->zookeeper_conn_num++;
            if ($this->zookeeper_conn_num >= self:: CONNECT_COUNT_RESET) {
                exit("连接zookeeper出错\n");
            }
            $this->get_zookeeper();
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
