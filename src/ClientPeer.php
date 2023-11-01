<?php

namespace YektaSmart\IotServer\Websocket;

use dnj\AAA\Contracts\IUser;
use YektaSmart\IotServer\Websocket\Contracts\IClientPeer;

class ClientPeer extends Peer implements IClientPeer
{
    /**
     * @param callable():\Swoole\WebSocket\Server $swooleResolver
     */
    public function __construct($swooleResolver, int $fd, protected int $deviceId, protected IUser $user)
    {
        parent::__construct($swooleResolver, $fd);
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
