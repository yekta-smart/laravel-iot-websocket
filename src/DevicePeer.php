<?php

namespace YektaSmart\IotServer\Websocket;

use YektaSmart\IotServer\Websocket\Contracts\IDevicePeer;

class DevicePeer extends Peer implements IDevicePeer
{
    /**
     * @param callable():\Swoole\WebSocket\Server $swooleResolver
     */
    public function __construct($swooleResolver, int $fd, protected int $deviceId)
    {
        parent::__construct($swooleResolver, $fd);
    }

    public function getDeviceId(): int
    {
        return $this->deviceId;
    }
}
