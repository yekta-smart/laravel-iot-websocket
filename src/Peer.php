<?php

namespace YektaSmart\IotServer\Websocket;

use YektaSmart\IotServer\Contracts\IPeerRegistery;
use YektaSmart\IotServer\Peer as IotServerPeer;
use YektaSmart\IotServer\Websocket\Contracts\IPeer;

class Peer extends IotServerPeer implements IPeer
{
    public function __construct(protected int $fd)
    {
        parent::__construct($fd);
    }

    public function getFd(): int
    {
        return $this->fd;
    }

    public function send(string $data): void
    {
        /**
         * @var \Swoole\WebSocket\Server $swoole
         */
        $swoole = app('swoole');

        if (!$swoole->push($this->fd, $data, SWOOLE_WEBSOCKET_OPCODE_BINARY)) {
            throw new \Exception();
        }
    }

    public function setEnvelopeType(string $type): void
    {
        parent::setEnvelopeType($type);
        $registery = app(IPeerRegistery::class);
        if ($registery instanceof PeerRegistery) {
            $registery->set($this);
        }
    }
}
