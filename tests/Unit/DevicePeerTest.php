<?php

namespace YektaSmart\IotServer\Websocket\Tests\Unit;

use YektaSmart\IotServer\Websocket\DevicePeer;
use YektaSmart\IotServer\Websocket\Tests\Concerns\TestsSwoole;
use YektaSmart\IotServer\Websocket\Tests\TestCase;

class DevicePeerTest extends TestCase
{
    use TestsSwoole;

    public function test(): void
    {
        $p = new DevicePeer($this->getSwooleResolver(), -1, 5);
        $this->assertSame(-1, $p->getFd());
        $this->assertSame(5, $p->getDeviceId());
    }
}
