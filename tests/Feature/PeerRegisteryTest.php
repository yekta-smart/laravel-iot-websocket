<?php

namespace YektaSmart\IotServer\Websocket\Tests\Feature;

use dnj\AAA\Models\User;
use Swoole\Table;
use Swoole\WebSocket\Server;
use YektaSmart\IotServer\Peer as IotServerPeer;
use YektaSmart\IotServer\Websocket\ClientPeer;
use YektaSmart\IotServer\Websocket\DevicePeer;
use YektaSmart\IotServer\Websocket\Peer;
use YektaSmart\IotServer\Websocket\PeerRegistery;
use YektaSmart\IotServer\Websocket\Tests\TestCase;

class PeerRegisteryTest extends TestCase
{
    public function setupTable(): Table
    {
        $table = new Table(1024);
        $table->column('fd', Table::TYPE_INT);
        $table->column('type', Table::TYPE_INT);
        $table->column('envelope', Table::TYPE_STRING, 128);
        $table->column('device_id', Table::TYPE_INT);
        $table->column('user_id', Table::TYPE_INT);
        $table->create();

        return $table;
    }

    public function buildRegistery(): PeerRegistery
    {
        /**
         * @var mixed
         */
        $swoole = $this->createStub(Server::class);
        $swoole->method('exists')->willReturn(true);

        return new PeerRegistery($swoole, $this->setupTable());
    }

    public function testAdd(): void
    {
        $registery = $this->buildRegistery();

        $devicePeer = new DevicePeer(1, 2);
        $this->assertFalse($registery->has($devicePeer));
        $this->assertFalse($registery->has(1));
        $registery->add($devicePeer);
        $this->assertTrue($registery->has($devicePeer));
        $this->assertTrue($registery->has(1));
        $this->assertTrue($registery->hasDevice(2));

        $clientPeer = new ClientPeer(3, 2, User::factory()->create());
        $this->assertFalse($registery->has($clientPeer));
        $this->assertFalse($registery->has(3));
        $registery->add($clientPeer);
        $this->assertTrue($registery->has($clientPeer));
        $this->assertTrue($registery->has(3));
        $this->assertTrue($registery->hasClient(2));

        $this->assertNull($registery->firstDevice(10));
        $this->assertNull($registery->firstClient(10));
        $this->assertEquals($devicePeer, $registery->firstDevice(2));
        $this->assertEquals($clientPeer->getId(), $registery->firstClient(2)->getId());
        $this->assertEmpty($registery->byDevice(10));
        $this->assertEmpty($registery->getClients(10));
        $this->assertCount(1, $registery->byDevice(2));
        $this->assertCount(1, $registery->getClients(2));

        $this->assertNull($registery->find(10));
        $this->assertEquals($devicePeer, $registery->find(1));
        $this->assertEquals($clientPeer->getId(), $registery->find(3)->getId());

        $this->expectException(\Exception::class);
        $registery->add($devicePeer);
    }

    public function testRemove(): void
    {
        $registery = $this->buildRegistery();

        $peer = new DevicePeer(1, 2);
        $this->assertFalse($registery->has($peer));
        $registery->add($peer);
        $this->assertTrue($registery->has($peer));
        $this->assertTrue($registery->remove(1));
        $this->assertFalse($registery->has($peer));
        $this->assertFalse($registery->has(1));
        $this->assertFalse($registery->remove($peer));
    }

    public function testFindOrFail(): void
    {
        $registery = $this->buildRegistery();

        $peer = new DevicePeer(1, 2);
        $registery->add($peer);
        $this->assertEquals($peer, $registery->findOrFail(1));

        $this->expectException(\Exception::class);
        $registery->findOrFail('2');
    }

    public function testFirstDeviceOrFail(): void
    {
        $registery = $this->buildRegistery();

        $peer = new DevicePeer(1, 2);
        $registery->add($peer);
        $this->assertEquals($peer, $registery->firstDeviceOrFail(2));

        $this->expectException(\Exception::class);
        $registery->firstDeviceOrFail(3);
    }

    public function testFirstClientOrFail(): void
    {
        $registery = $this->buildRegistery();

        $user = User::factory()->create();
        $peer = new ClientPeer(1, 2, $user);
        $registery->add($peer);
        $this->assertSame($peer->getId(), $registery->firstClientOrFail(2)->getId());
        $this->assertSame($user->id, $registery->firstClientOrFail(2)->getUser()->id);

        $this->expectException(\Exception::class);
        $registery->firstClientOrFail(3);
    }

    public function testReplaceTwoUnidenticalPeers(): void
    {
        $registery = $this->buildRegistery();

        $peer1 = new DevicePeer(1, 2);
        $peer2 = new DevicePeer('10', 2);

        $this->expectException(\Exception::class);
        $registery->replace($peer1, $peer2);
    }

    public function testReplaceUnpresentCurrentPeer(): void
    {
        $registery = $this->buildRegistery();

        $peer1 = new Peer(1);
        $peer2 = new DevicePeer(1, 2);

        $this->expectException(\Exception::class);
        $registery->replace($peer1, $peer2);
    }

    public function testReplace(): void
    {
        $registery = $this->buildRegistery();

        $peer1 = new Peer(1);
        $peer2 = new DevicePeer(1, 2);

        $registery->add($peer1);
        $this->assertEquals($peer1, $registery->findOrFail(1));

        $registery->replace($peer1, $peer2);
        $this->assertEquals($peer2, $registery->findOrFail(1));
    }

    public function testAddNonSwoolePeer(): void
    {
        $registery = $this->buildRegistery();

        $peer1 = new IotServerPeer(1);

        $this->expectException(\TypeError::class);
        $registery->add($peer1);
    }
}
