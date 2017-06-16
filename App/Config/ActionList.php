<?php

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
            'alias' => array(),
            'option' => array()
        )
    ),
    'addMethods' => array(), //指定类里面public方法来发布
    'addInstanceMethods' => array(//$object 对象上所在类上声明的所有 public 实例方法
        array(
            'func' => new App\Action\User(),
            'class' => '',
            'alias' => 'RPC_User',
            'option' => array()
        )
    ),
    'addClassMethods' => array(//静态方法发布
        array(
            'func' => new App\Action\St(),
            'class' => '',
            'alias' => 'RPC_St',
            'option' => array()
        )
    )
);