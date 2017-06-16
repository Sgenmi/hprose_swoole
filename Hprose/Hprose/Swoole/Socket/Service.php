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
 * Hprose/Swoole/Socket/Service.php                       *
 *                                                        *
 * hprose swoole socket service library for php 5.3+      *
 *                                                        *
 * LastModified: Dec 15, 2016                             *
 * Author: Ma Bingyao <andot@hprose.com>                  *
 *                                                        *
\**********************************************************/

namespace Hprose\Swoole\Socket;

use stdClass;
use Exception;
use Throwable;
use Hprose\Swoole\Timer;

class Service extends \Hprose\Service {
    const MAX_PACK_LEN = 0x200000;
    public $onAccept = null;
    public $onClose = null;
    public function __construct() {
        parent::__construct();
        $this->timer = new Timer();
    }
    private function send($server, $socket, $data) {
        if ($server->exist($socket)) {
            return $server->send($socket, $data);
        }
        return false;
    }
    public function socketSend($server, $socket, $data, $id) {
        $dataLength = strlen($data);
        if ($id === null) {
            $this->send($server, $socket, pack("N", $dataLength));
        }
        else {
            $this->send($server, $socket, pack("NN", $dataLength | 0x80000000, $id));
        }
        if ($dataLength <= self::MAX_PACK_LEN) {
            return $this->send($server, $socket, $data);
        }
        else {
            for ($i = 0; $i < $dataLength; $i += self::MAX_PACK_LEN) {
                if (!$this->send($server, $socket, substr($data, $i, min($dataLength - $i, self::MAX_PACK_LEN)))) {
                    return false;
                }
            }
            return true;
        }
    }
    public function getOnReceive() {
        $self = $this;
        $bytes = '';
        $headerLength = 4;
        $dataLength = -1;
        $id = null;
        return function($server, $socket, $fromid, $data)
                use ($self, &$bytes, &$headerLength, &$dataLength, &$id) {
            $bytes .= $data;
            while (true) {
                $length = strlen($bytes);
                if (($dataLength < 0) && ($length >= $headerLength)) {
                    list(, $dataLength) = unpack('N', substr($bytes, 0, 4));
                    if (($dataLength & 0x80000000) !== 0) {
                        $dataLength &= 0x7FFFFFFF;
                        $headerLength = 8;
                    }
                }
                if (($headerLength === 8) && ($id === null) && ($length >= $headerLength)) {
                    list(, $id) = unpack('N', substr($bytes, 4, 4));
                }
                if (($dataLength >= 0) && (($length - $headerLength) >= $dataLength)) {
                    $context = new stdClass();
                    $context->server = $server;
                    $context->socket = $socket;
                    $context->fd = $socket;
                    $context->fromid = $fromid;
                    $context->userdata = new stdClass();
                    $data = substr($bytes, $headerLength, $dataLength);
                    $self->userFatalErrorHandler = function($error) use ($self, $server, $socket, $id, $context) {
                        $self->socketSend($server, $socket, $self->endError($error, $context), $id);
                    };
                    $self->defaultHandle($data, $context)->then(function($data) use ($self, $server, $socket, $id) {
                        $self->socketSend($server, $socket, $data, $id);
                    });
                    $bytes = substr($bytes, $headerLength + $dataLength);
                    $id = null;
                    $headerLength = 4;
                    $dataLength = -1;
                }
                else {
                    break;
                }
            }
        };
    }
    public function socketHandle($server) {
        $self = $this;
        $onReceives = array();
        $server->on('connect', function($server, $socket, $fromid) use ($self, &$onReceives) {
            $onReceives[$socket] = $self->getOnReceive();
            $context = new stdClass();
            $context->server = $server;
            $context->socket = $socket;
            $context->fd = $socket;
            $context->fromid = $fromid;
            $context->userdata = new stdClass();
            try {
                $onAccept = $self->onAccept;
                if (is_callable($onAccept)) {
                    call_user_func($onAccept, $context);
                }
            }
            catch (Exception $e) { $server->close($socket); }
            catch (Throwable $e) { $server->close($socket); }
        });
        $server->on('close', function($server, $socket, $fromid) use ($self, &$onReceives) {
            unset($onReceives[$socket]);
            $context = new stdClass();
            $context->server = $server;
            $context->socket = $socket;
            $context->fd = $socket;
            $context->fromid = $fromid;
            $context->userdata = new stdClass();
            try {
                $onClose = $self->onClose;
                if (is_callable($onClose)) {
                    call_user_func($onClose, $context);
                }
            }
            catch (Exception $e) {}
            catch (Throwable $e) {}
        });
        $server->on("receive", function ($server, $socket, $fromid, $data) use(&$onReceives) {
            if (isset($onReceives[$socket])) {
                $onReceive = $onReceives[$socket];
                $onReceive($server, $socket, $fromid, $data);
            }
            else {
                $server->close($socket, true);
            }
        });
    }
}

