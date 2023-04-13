<?php

namespace YektaSmart\IotServer\Websocket;

use dnj\AAA\Contracts\IUser;
use Hhxsv5\LaravelS\Swoole\WebSocketHandlerInterface;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Gate;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use YektaSmart\IotServer\Contracts\IDeviceManager;
use YektaSmart\IotServer\Contracts\IPeerRegistery;
use YektaSmart\IotServer\Contracts\IPostOffice;

class WSHandler implements WebSocketHandlerInterface
{
    protected IPostOffice $postOffice;
    protected IPeerRegistery $peerRegistery;

    public function __construct()
    {
        $this->peerRegistery = app(IPeerRegistery::class);
        $this->postOffice = app(IPostOffice::class);
    }

    public function trySendBinaryForFd(int $fd, string $data): bool
    {
        /**
         * @var \Swoole\WebSocket\Server $swoole
         */
        $swoole = app('swoole');

        return $swoole->push($fd, $data, SWOOLE_WEBSOCKET_OPCODE_BINARY);
    }

    public function sendBinaryForFd(int $fd, string $data): void
    {
        if (!$this->trySendBinaryForFd($fd, $data)) {
            throw new \Exception();
        }
    }

    public function onOpen(Server $server, Request $swooleRequest)
    {
        $request = request();
        if ($request->query('auth')) {
            $request->headers->set('Authorization', 'Bearer '.$request->query('auth'));
        }
        $this->authenticate($request, [null, 'sanctum']);

        /**
         * @var IUser|null
         */
        $user = $request->user();
        $peer = null;
        if ($request->query('device')) {
            /**
             * @var IDeviceManager
             */
            $deviceManager = app(IDeviceManager::class);
            $device = $deviceManager->findOrFail($request->query('device'));
            Gate::authorize('view', $device);

            $peer = new ClientPeer($swooleRequest->fd, $device->getId(), $user);
        }
        if (!$peer) {
            $peer = new Peer($swooleRequest->fd);
        }
        $this->peerRegistery->add($peer);
    }

    public function onMessage(Server $server, Frame $frame)
    {
        $peer = $this->peerRegistery->find($frame->fd);
        if (!$peer) {
            $peer = new Peer($frame->fd);
            $this->peerRegistery->add($peer);
        }
        $this->postOffice->receive($peer, $frame->data);
    }

    public function onClose(Server $server, $fd, $reactorId)
    {
        $this->peerRegistery->remove($fd);
    }

    protected function authenticate(HttpRequest $request, array $guards)
    {
        if (empty($guards)) {
            $guards = [null];
        }

        /**
         * @var \Illuminate\Auth\AuthManager
         */
        $auth = app(Auth::class);
        $request->setUserResolver($auth->userResolver());
        foreach ($guards as $guard) {
            if ($auth->guard($guard)->check()) {
                return $auth->shouldUse($guard);
            }
        }

        $this->unauthenticated($request, $guards);
    }

    protected function unauthenticated($request, array $guards)
    {
    }
}
