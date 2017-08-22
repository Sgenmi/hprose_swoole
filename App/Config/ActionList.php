<?php


 HttpServer::$db_config = [
        'server' => "127.0.0.1",
        'port' => 3306,
        'username' => 'root',
        'password' => '123456',
        'database_name' => 'magento',
        'database_type' => 'mysql',
        'prefix' => 'catalog_',
        'debug_mode' => false
    ];


HttpServer::$action_list = array(
    'addMissingFunction' => array(//当客户端调用未发布的方法时调用
        array(
            'func' => 'missFunction',
            'option' => array()
        )
    ),
    'addFunction' => array(//单个方法发布
//        array(
//            'func' => 'xxx',
//            'alias' => '',
//            'option' => array()
//        )
    ),
    'addFunctions' => array(//多个方法发布
        array(
            'func' => array( 'hello','reload'),
            'alias' => array('fn_hello','fn_reload'),
            'option' => array()
        )
    ),
    'addMethods' => array(), //指定类里面public方法来发布
    'addInstanceMethods' => array(//$object 对象上所在类上声明的所有 public 实例方法
        array(
            'func' => new App\Controller\User(),
            'class' => '',
            'alias' => 'Cls_User',
            'option' => array()
        )
    ),
    'addClassMethods' => array(//静态方法发布
        array(
            'func' => new App\Controller\St(),
            'class' => '',
            'alias' => 'Cls_St',
            'option' => array()
        )
    )
);