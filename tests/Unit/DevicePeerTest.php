<?php

namespace YektaSmart\IotServer\Websocket\Tests\Unit;

use YektaSmart\IotServer\Websocket\DevicePeer;
use YektaSmart\IotServer\Websocket\Tests\TestCase;

class DevicePeerTest extends TestCase
{
    public function test(): void
    {
        $p = new DevicePeer(-1, 5);
        $this->assertSame(-1, $p->getFd());
        $this->assertSame(5, $p->getDeviceId());
    }
}
