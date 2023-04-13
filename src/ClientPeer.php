<?php

namespace YektaSmart\IotServer\Websocket;

use dnj\AAA\Contracts\IUser;
use YektaSmart\IotServer\Websocket\Contracts\IClientPeer;

class ClientPeer extends Peer implements IClientPeer
{
    public function __construct(int $fd, protected int $deviceId, protected IUser $user)
    {
        parent::__construct($fd);
    }

    public function getDeviceId(): int
    {
        return $this->deviceId;
    }

    public function getUser(): IUser
    {
        return $this->user;
    }
}
