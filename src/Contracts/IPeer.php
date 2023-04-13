<?php

namespace YektaSmart\IotServer\Websocket\Contracts;

use YektaSmart\IotServer\Contracts\IPeer as IBasePeer;

interface IPeer extends IBasePeer
{
    public function getFd(): int;
}
