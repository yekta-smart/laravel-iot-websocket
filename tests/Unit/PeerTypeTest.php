<?php

namespace YektaSmart\IotServer\Websocket\Tests\Unit;

use YektaSmart\IotServer\Websocket\DevicePeer;
use YektaSmart\IotServer\Websocket\Peer;
use YektaSmart\IotServer\Websocket\PeerType;
use YektaSmart\IotServer\Websocket\Tests\TestCase;

class PeerTypeTest extends TestCase
{
    public function test(): void
    {
        $this->assertSame(PeerType::UNKNOWN, PeerType::fromPeer(new Peer(0)));
        $this->assertSame(PeerType::DEVICE, PeerType::fromPeer(new DevicePeer(0, 1)));
    }
}
