<?php

namespace YektaSmart\IotServer\Websocket\LaravelSwoole;

use dnj\AAA\Contracts\IUser;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Gate;
use Swoole\WebSocket\Frame;
use SwooleTW\Http\Server\Facades\Server;
use SwooleTW\Http\Websocket\HandlerContract;
use YektaSmart\IotServer\Contracts\IDeviceManager;
use YektaSmart\IotServer\Contracts\IPeerRegistery;
use YektaSmart\IotServer\Contracts\IPostOffice;
use YektaSmart\IotServer\Websocket\ClientPeer;
use YektaSmart\IotServer\Websocket\Concerns\WorksWithSwoole;
use YektaSmart\IotServer\Websocket\Peer;

class WSHandler implements HandlerContract
{
    use WorksWithSwoole;

    protected IPeerRegistery $peerRegistery;

    public function __construct()
    {
        $this->peerRegistery = app(IPeerRegistery::class);
        $this->swooleResolver = fn () => app()->make(Server::class);
    }

    public function trySendBinaryForFd(int $fd, string $data): bool
    {
        return $this->resolveSwoole()->push($fd, $data, SWOOLE_WEBSOCKET_OPCODE_BINARY);
    }

    public function sendBinaryForFd(int $fd, string $data): void
    {
        if (!$this->trySendBinaryForFd($fd, $data)) {
            throw new \Exception();
        }
    }

    public function onOpen($fd, HttpRequest $request): bool
    {
        /**
         * @var IUser|null
         */
        $user = $request->user();
        if ($request->query('device')) {
            if (!$user) {
                $this->disconnect($fd, 4401);

                return false;
            }

            /**
             * @var IDeviceManager
             */
            $deviceManager = app(IDeviceManager::class);
            $device = $deviceManager->findOrFail($request->query('device'));
            Gate::authorize('view', $device);

            if (!$this->peerRegistery->hasDevice($device->getId())) {
                $this->disconnect($fd, 4504);

                return false;
            }
            $peer = new ClientPeer($this->swooleResolver, $fd, $device->getId(), $user);
        }
        if (!isset($peer)) {
            $peer = new Peer($this->swooleResolver, $fd);
        }
        $this->peerRegistery->add($peer);

        return true;
    }

    public function onMessage(Frame $frame)
    {
        $peer = $this->peerRegistery->find($frame->fd);
        if (!$peer) {
            $this->disconnect($frame->fd, true);

            return;
        }
        app(IPostOffice::class)->receive($peer, $frame->data);
    }

    public function onClose($fd, $reactorId)
    {
        $this->peerRegistery->remove($fd);
    }

    protected function disconnect(int $fd, int $code = SWOOLE_WEBSOCKET_CLOSE_NORMAL): void
    {
        $this->resolveSwoole()->disconnect($fd, $code);
    }
}
