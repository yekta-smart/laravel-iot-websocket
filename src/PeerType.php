<?php

namespace YektaSmart\IotServer\Websocket;

use YektaSmart\IotServer\Contracts\IClientPeer;
use YektaSmart\IotServer\Contracts\IDevicePeer;
use YektaSmart\IotServer\Contracts\IPeer;

enum PeerType: int
{
    case UNKNOWN = 1;
    case DEVICE = 2;
    case CLIENT = 3;

    public static function fromPeer(IPeer $peer): self
    {
        if ($peer instanceof IDevicePeer) {
            return self::DEVICE;
        }
        if ($peer instanceof IClientPeer) {
            return self::CLIENT;
        }

        return self::UNKNOWN;
    }
}
