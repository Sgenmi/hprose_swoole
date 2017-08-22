<?php
/**********************************************************\
|                                                          |
|                          hprose                          |
|                                                          |
| Official WebSite: http://www.hprose.com/                 |
|                   http://www.hprose.org/                 |
|                                                          |
\**********************************************************/

/**********************************************************\
 *                                                        *
 * Hprose/Swoole/Socket/Client.php                        *
 *                                                        *
 * hprose swoole socket client library for php 5.3+       *
 *                                                        *
 * LastModified: Nov 25, 2016                             *
 * Author: Ma Bingyao <andot@hprose.com>                  *
 *                                                        *
\**********************************************************/

namespace Hprose\Swoole\Socket;

use stdClass;
use Exception;
use Hprose\Future;

class Client extends \Hprose\Client {
    public $type;
    public $host = "";
    public $port = 0;
    public $fullDuplex = false;
    public $maxPoolSize = 10;
    public $poolTimeout = 30000;
    public $noDelay = true;
    public $settings = array();
    private $fdtrans;
    private $hdtrans;
    public function __construct($uris = null) {
        parent::__construct($uris);
        swoole_async_set(array(
            "socket_buffer_size" => 2 * 1024 * 1024 * 1024 - 1,
            "socket_dontwait" => false
        ));
    }
    public function getHost() {
        return $this->host;
    }
    public function getPort() {
        return $this->port;
    }
    public function getType() {
        return $this->type;
    }
    public function setFullDuplex($value) {
        $this->fullDuplex = $value;
    }
    public function isFullDuplex() {
        return $this->fullDuplex;
    }
    public function setNoDelay($value) {
        $this->noDelay = $value;
    }
    public function isNoDelay() {
        return $this->noDelay;
    }
    public function setMaxPoolSize($value) {
        $this->maxPoolSize = $value;
    }
    public function getMaxPoolSize() {
        return $this->maxPoolSize;
    }
    public function setPoolTimeout($value) {
        $this->poolTimeout = $value;
    }
    public function getPoolTimeout() {
        return $this->poolTimeout;
    }
    public function set($key, $value) {
        $this->settings[$key] = $value;
        return $this;
    }
    protected function setUri($uri) {
        parent::setUri($uri);
        $p = parse_url($uri);
        if ($p) {
            switch (strtolower($p['scheme'])) {
                case 'tcp':
                case 'tcp4':
                    $this->type = SWOOLE_SOCK_TCP;
                    $this->host = $p['host'];
                    $this->port = $p['port'];
                    break;
                case 'tcp6':
                    $this->type = SWOOLE_SOCK_TCP6;
                    $this->host = $p['host'];
                    $this->port = $p['port'];
                    break;
                case 'ssl':
                case 'sslv2':
                case 'sslv3':
                case 'tls':
                    $this->type = SWOOLE_SOCK_TCP | SWOOLE_SSL;
                    $this->host = $p['host'];
                    $this->port = $p['port'];
                    break;
                case 'unix':
                    $this->type = SWOOLE_UNIX_STREAM;
                    $this->host = $p['path'];
                    $this->port = 0;
                    break;
                default:
                    throw new Exception("Only support tcp, tcp4, tcp6 or unix scheme");
            }
            if ((($this->type === SWOOLE_SOCK_TCP) ||
                 ($this->type === SWOOLE_SOCK_TCP | SWOOLE_SSL)) &&
                (filter_var($this->host, FILTER_VALIDATE_IP) === false)) {
                $ip = gethostbyname($this->host);
                if ($ip === $this->host) {
                    throw new Exception('DNS lookup failed');
                }
                else {
                    $this->host = $ip;
                }
            }
            $this->close();
        }
        else {
            throw new Exception("Can't parse this uri: " . $uri);
        }
    }
    public function close() {
        if (isset($this->fdtrans)) {
            $this->fdtrans->close();
        }
        if (isset($this->hdtrans)) {
            $this->hdtrans->close();
        }
    }
    protected function wait($interval, $callback) {
        $future = new Future();
        swoole_timer_after($interval * 1000, function() use ($future, $callback) {
            Future\sync($callback)->fill($future);
        });
        return $future;
    }
    protected function sendAndReceive($request, stdClass $context) {
        $future = new Future();
        if ($this->fullDuplex) {
            if (($this->fdtrans === null) || ($this->fdtrans->uri !== $this->uri)) {
                $this->fdtrans = new FullDuplexTransporter($this);
            }
            $this->fdtrans->sendAndReceive($request, $future, $context);
        }
        else {
            if (($this->hdtrans === null) || ($this->hdtrans->uri !== $this->uri)) {
                $this->hdtrans = new HalfDuplexTransporter($this);
            }
            $this->hdtrans->sendAndReceive($request, $future, $context);
        }
        if ($context->oneway) {
            $future->resolve(null);
        }
        return $future;
    }
}
