<?php

namespace YektaSmart\IotServer\Websocket;

use YektaSmart\IotServer\Contracts\IPeerRegistery;
use YektaSmart\IotServer\Peer as IotServerPeer;
use YektaSmart\IotServer\Websocket\Concerns\WorksWithSwoole;
use YektaSmart\IotServer\Websocket\Contracts\IPeer;

class Peer extends IotServerPeer implements IPeer
{
    use WorksWithSwoole;

    /**
     * @param callable():\Swoole\WebSocket\Server $swooleResolver
     */
    public function __construct($swooleResolver, protected int $fd)
    {
        parent::__construct($fd);
        $this->swooleResolver = $swooleResolver;
    }

    public function getFd(): int
    {
        return $this->fd;
    }

    public function send(string $data): void
    {
        if (!$this->resolveSwoole()->push($this->fd, $data, SWOOLE_WEBSOCKET_OPCODE_BINARY)) {
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
