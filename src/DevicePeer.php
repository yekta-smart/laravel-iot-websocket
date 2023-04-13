<?php

namespace YektaSmart\IotServer\Websocket;

use YektaSmart\IotServer\Websocket\Contracts\IDevicePeer;

class DevicePeer extends Peer implements IDevicePeer
{
    public function __construct(int $fd, protected int $deviceId)
    {
        parent::__construct($fd);
    }

    public function getDeviceId(): int
    {
        return $this->deviceId;
    }
}
