<?php

namespace YektaSmart\IotServer\Websocket\Tests\Unit;

use Swoole\WebSocket\Server;
use YektaSmart\IotServer\Websocket\Peer;
use YektaSmart\IotServer\Websocket\Tests\TestCase;

class PeerTest extends TestCase
{
    public function test(): void
    {
        $p = new Peer(0);
        $this->assertSame(0, $p->getFd());
        $this->assertFalse($p->hasEnvelopeType());
        $p->setEnvelopeType(\stdClass::class);
        $this->assertTrue($p->hasEnvelopeType());
        $this->assertSame($p->getEnvelopeType(\stdClass::class), $p->getEnvelopeType());
    }

    public function testGetEnvelopeType(): void
    {
        $p = new Peer(0);
        $this->expectException(\Exception::class);
        $p->getEnvelopeType();
    }

    public function testSend(): void
    {
        $server = $this->createMock(Server::class);
        $server->expects($this->once())
            ->method('push')
            ->with(0, 'hi', SWOOLE_WEBSOCKET_OPCODE_BINARY)
            ->willReturn(true);
        $this->app->singleton('swoole', fn () => $server);
        $p = new Peer(0);
        $p->send('hi');
    }

    public function testSendFailed(): void
    {
        $server = $this->createMock(Server::class);
        $server->expects($this->once())
            ->method('push')
            ->with(0, 'hi', SWOOLE_WEBSOCKET_OPCODE_BINARY)
            ->willReturn(false);
        $this->app->singleton('swoole', fn () => $server);
        $p = new Peer(0);

        $this->expectException(\Exception::class);
        $p->send('hi');
    }
}
