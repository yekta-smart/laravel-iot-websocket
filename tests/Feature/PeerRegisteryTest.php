<?php

namespace YektaSmart\IotServer\Websocket\Tests\Feature;

use Swoole\Table;
use YektaSmart\IotServer\Peer as IotServerPeer;
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
        $table->create();

        return $table;
    }

    public function testAdd(): void
    {
        $registery = new PeerRegistery($this->setupTable());

        $peer = new DevicePeer(1, 2);
        $this->assertFalse($registery->has($peer));
        $this->assertFalse($registery->has(1));
        $registery->add($peer);
        $this->assertTrue($registery->has($peer));
        $this->assertTrue($registery->has(1));
        $this->assertTrue($registery->hasDevice(2));

        $this->assertNull($registery->firstDevice(10));
        $this->assertEquals($peer, $registery->firstDevice(2));
        $this->assertEmpty($registery->byDevice(10));
        $this->assertCount(1, $registery->byDevice(2));

        $this->assertNull($registery->find(10));
        $this->assertEquals($peer, $registery->find(1));

        $this->expectException(\Exception::class);
        $registery->add($peer);
    }

    public function testRemove(): void
    {
        $registery = new PeerRegistery($this->setupTable());

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
        $registery = new PeerRegistery($this->setupTable());

        $peer = new DevicePeer(1, 2);
        $registery->add($peer);
        $this->assertEquals($peer, $registery->findOrFail(1));

        $this->expectException(\Exception::class);
        $registery->findOrFail('2');
    }

    public function testFirstDeviceOrFail(): void
    {
        $registery = new PeerRegistery($this->setupTable());

        $peer = new DevicePeer(1, 2);
        $registery->add($peer);
        $this->assertEquals($peer, $registery->firstDeviceOrFail(2));

        $this->expectException(\Exception::class);
        $registery->firstDeviceOrFail(3);
    }

    public function testReplaceTwoUnidenticalPeers(): void
    {
        $registery = new PeerRegistery($this->setupTable());

        $peer1 = new DevicePeer(1, 2);
        $peer2 = new DevicePeer('10', 2);

        $this->expectException(\Exception::class);
        $registery->replace($peer1, $peer2);
    }

    public function testReplaceUnpresentCurrentPeer(): void
    {
        $registery = new PeerRegistery($this->setupTable());

        $peer1 = new Peer(1);
        $peer2 = new DevicePeer(1, 2);

        $this->expectException(\Exception::class);
        $registery->replace($peer1, $peer2);
    }

    public function testReplace(): void
    {
        $registery = new PeerRegistery($this->setupTable());

        $peer1 = new Peer(1);
        $peer2 = new DevicePeer(1, 2);

        $registery->add($peer1);
        $this->assertEquals($peer1, $registery->findOrFail(1));

        $registery->replace($peer1, $peer2);
        $this->assertEquals($peer2, $registery->findOrFail(1));
    }

    public function testAddNonSwoolePeer(): void
    {
        $registery = new PeerRegistery($this->setupTable());

        $peer1 = new IotServerPeer(1);

        $this->expectException(\TypeError::class);
        $registery->add($peer1);
    }
}
