<?php


/**
 * Description of Qstatic
 *
 * @author Sgenmi
 * @date 2017-8-22
 * @Email 150560159@qq.com
 */
class Qstatic {

    public function __construct() { }

    public function getMillisecond($sTime = 0, $eTime = 0) {
        $time = $eTime - $sTime;
        return round($time, 4) * 1000;
    }
    private function sendData($action="",$time=0) {
        $cli = new Swoole\Http\Client('127.0.0.1', 4466);
        $cli->setHeaders(array('User-Agent' => 'swoole-http-client'));

        $cli->post('/stat/receive', array("time" =>$time ,'action'=>$action), function ($cli) {});
    }

    public function asynchandle($name, array &$args, stdClass $context, Closure $next) {
        $startTime = microtime(true);
        yield $next($name, $args, $context);
        $endTime = microtime(true);
        $time = $this->getMillisecond($startTime, $endTime);
        $this->sendData($name,$time );
    }

    public function synchandle($name, array &$args, stdClass $context, Closure $next) {
        $startTime = microtime(true);
        $response = $next($name, $args, $context);
        $endTime = microtime(true);
        
        $time = $this->getMillisecond($startTime, $endTime);
        $this->sendData($name,$time );
        
        return $response;
    }

}