<?php

namespace YektaSmart\IotServer\Websocket;

use dnj\AAA\Contracts\IUserManager;
use Swoole\Server;
use Swoole\Table;
use YektaSmart\IotServer\Contracts\IClientPeer;
use YektaSmart\IotServer\Contracts\IDevice;
use YektaSmart\IotServer\Contracts\IDevicePeer;
use YektaSmart\IotServer\Contracts\IPeer;
use YektaSmart\IotServer\Contracts\IPeerRegistery;
use YektaSmart\IotServer\Models\Device;
use YektaSmart\IotServer\Websocket\Contracts\IPeer as ISwoolePeer;

class PeerRegistery implements IPeerRegistery
{
    protected Table $peers;
    protected Server $swoole;

    public function __construct(?Server $swoole = null, ?Table $peersTable = null)
    {
        $this->swoole = $swoole ?? app('swoole');
        $this->peers = $peersTable ?? $this->swoole->peersTable;
    }

    public function add(IPeer $peer): void
    {
        if ($this->has($peer)) {
            throw new \Exception('Peer already is present in registry');
        }
        $this->set($peer);
    }

    public function replace(IPeer|string $current, IPeer $new): void
    {
        if ($current instanceof IPeer) {
            $current = $current->getId();
        }
        if ($current != $new->getId()) {
            throw new \Exception('peers does not same id');
        }

        if (!$this->has($current)) {
            throw new \Exception('current peer does not present in registry');
        }

        $this->remove($new);
        $this->set($new);
    }

    public function firstDevice(int|IDevice $device): ?IDevicePeer
    {
        $device = Device::ensureId($device);
        foreach ($this->all() as $peer) {
            if ($peer instanceof IDevicePeer and $peer->getDeviceId() === $device) {
                return $peer;
            }
        }

        return null;
    }

    public function firstDeviceOrFail(int|IDevice $device): IDevicePeer
    {
        $p = $this->firstDevice($device);
        if (null === $p) {
            throw new \Exception('Notfound');
        }

        return $p;
    }

    /**
     * @return IDevicePeer[]
     */
    public function byDevice(int|IDevice $device): array
    {
        $peers = [];
        $device = Device::ensureId($device);
        foreach ($this->all() as $peer) {
            if ($peer instanceof IDevicePeer and $peer->getDeviceId() === $device) {
                $peers[] = $peer;
            }
        }

        return $peers;
    }

    public function hasDevice(int|IDevice $device): bool
    {
        return null !== $this->firstDevice($device);
    }

    public function firstClient(int|IDevice $device): ?IClientPeer
    {
        $device = Device::ensureId($device);
        foreach ($this->all() as $peer) {
            if ($peer instanceof IClientPeer and $peer->getDeviceId() === $device) {
                return $peer;
            }
        }

        return null;
    }

    public function firstClientOrFail(int|IDevice $device): IClientPeer
    {
        $p = $this->firstClient($device);
        if (null === $p) {
            throw new \Exception('Notfound');
        }

        return $p;
    }

    /**
     * @return IClientPeer[]
     */
    public function getClients(int|IDevice $device): array
    {
        $peers = [];
        $device = Device::ensureId($device);
        foreach ($this->all() as $peer) {
            if ($peer instanceof IClientPeer and $peer->getDeviceId() === $device) {
                $peers[] = $peer;
            }
        }

        return $peers;
    }

    public function hasClient(int|IDevice $device): bool
    {
        return null !== $this->firstClient($device);
    }

    public function has(IPeer|string $peer): bool
    {
        if ($peer instanceof IPeer) {
            $peer = $peer->getId();
        }

        return $this->peers->exists($peer);
    }

    public function find(string $id): ?ISwoolePeer
    {
        return $this->has($id) ? $this->buildPeer($this->peers->get($id)) : null;
    }

    public function findOrFail(string $id): ISwoolePeer
    {
        $peer = $this->find($id);
        if (!$peer) {
            throw new \Exception('Notfound');
        }

        return $peer;
    }

    public function remove(IPeer|string $peer): bool
    {
        if ($peer instanceof IPeer) {
            $peer = $peer->getId();
        }
        if (!$this->has($peer)) {
            return false;
        }

        return $this->peers->del($peer);
    }

    /**
     * @return \Generator<IPeer>
     */
    public function all(): \Generator
    {
        foreach ($this->peers as $data) {
            if (!$this->swoole->exists($data['fd'])) {
                $this->peers->del($data['fd']);
                continue;
            }
            yield $this->buildPeer($data);
        }
    }

    protected function set(IPeer $peer): void
    {
        $this->ensureSwoolePeer($peer);

        /**
         * @var ISwoolePeer $peer
         */
        $data = [
            'fd' => $peer->getFd(),
            'type' => PeerType::fromPeer($peer)->value,
            'envelope' => $peer->hasEnvelopeType() ? $peer->getEnvelopeType() : '',
            'device_id' => ($peer instanceof IDevicePeer or $peer instanceof IClientPeer) ? $peer->getDeviceId() : 0,
            'user_id' => $peer instanceof IClientPeer ? intval($peer->getUser()->getAuthIdentifier()) : 0,
        ];
        $this->peers->set($peer->getId(), $data);
    }

    protected function buildPeer(array $data): ISwoolePeer
    {
        switch ($data['type']) {
            case PeerType::DEVICE->value:
                $peer = new DevicePeer($data['fd'], $data['device_id']);
                break;
            case PeerType::CLIENT->value:
                $user = app(IUserManager::class)->findOrFail($data['user_id']);
                $peer = new ClientPeer($data['fd'], $data['device_id'], $user);
                break;
            case PeerType::UNKNOWN->value:
                $peer = new Peer($data['fd']);
                break;
            default:
                throw new \Exception('Unknown peer type in swoole table');
        }

        if ($data['envelope']) {
            $peer->setEnvelopeType($data['envelope']);
        }

        return $peer;
    }

    protected function ensureSwoolePeer(IPeer $peer): void
    {
        if (!$peer instanceof ISwoolePeer) {
            throw new \TypeError('Only working with '.ISwoolePeer::class);
        }
    }
}
