<?php

spl_autoload_register(function ($clsName){
    static $cls_arr=array();
    if(strpos($clsName, 'App')!==FALSE)
    {
        $cls_key =$clsName ;
        if(!isset($cls_arr[$cls_key])){
            $file = APP_PATH.str_replace("App","" ,str_replace("\\", "/", $clsName)).".php";
            $cls_arr[$cls_key] = true;
            include   $file;
        }  
    }
});
require APP_PATH.'/Config/ActionList.php';