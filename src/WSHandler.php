<?php

namespace YektaSmart\IotServer\Websocket;

use dnj\AAA\Contracts\IUser;
use Hhxsv5\LaravelS\Swoole\WebSocketHandlerInterface;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Gate;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
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

    public function onHandShake(SwooleRequest $request, SwooleResponse $response)
    {
        if (!isset($request->header['sec-websocket-key'])) {
            // Bad protocol implementation: it is not RFC6455.
            $response->end();

            return;
        }
        $secKey = $request->header['sec-websocket-key'];
        if (!preg_match('#^[+/0-9A-Za-z]{21}[AQgw]==$#', $secKey) || 16 !== strlen(base64_decode($secKey))) {
            // Header Sec-WebSocket-Key is illegal;
            $response->end();

            return;
        }
        if (isset($request->get['device'])) {
            $laravelRequest = request();
            if (isset($request->get['auth'])) {
                $laravelRequest->headers->set('authorization', 'Bearer '.$request->get['auth']);
            }
            if ($laravelRequest->header('authorization')) {
                $auth = app(Auth::class);
                $laravelRequest->setUserResolver($auth->userResolver());
                if ($auth->guard('sanctum')->check()) {
                    $auth->shouldUse('sanctum');
                }
            }

            /**
             * @var IDeviceManager
             */
            $deviceManager = app(IDeviceManager::class);
            $device = $deviceManager->findOrFail($request->get['device']);
            Gate::authorize('view', $device);

            if (!$this->peerRegistery->hasDevice($device->getId())) {
                $response->status(504);
                $response->end();

                return;
            }
        }

        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => base64_encode(sha1($secKey.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)),
            'Sec-WebSocket-Version' => '13',
        ];

        // WebSocket connection to 'ws://127.0.0.1:5200/'
        // failed: Error during WebSocket handshake:
        // Response must not include 'Sec-WebSocket-Protocol' header if not present in request: websocket
        if (isset($request->header['sec-websocket-protocol'])) {
            $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
        }

        foreach ($headers as $key => $value) {
            $response->header($key, $value);
        }

        $response->status(101);
        $response->end();
    }

    public function onOpen(Server $server, SwooleRequest $swooleRequest)
    {
        $request = request();
        /**
         * @var IUser|null
         */
        $user = $request->user();
        if ($request->query('device')) {
            if (!$user) {
                $server->disconnect($swooleRequest->fd, 4401);

                return;
            }

            /**
             * @var IDeviceManager
             */
            $deviceManager = app(IDeviceManager::class);
            $device = $deviceManager->findOrFail($request->query('device'));
            Gate::authorize('view', $device);

            if (!$this->peerRegistery->hasDevice($device->getId())) {
                $server->disconnect($swooleRequest->fd, 4504);

                return;
            }
            $peer = new ClientPeer($swooleRequest->fd, $device->getId(), $user);
        }
        if (!isset($peer)) {
            $peer = new Peer($swooleRequest->fd);
        }
        $this->peerRegistery->add($peer);
    }

    public function onMessage(Server $server, Frame $frame)
    {
        $peer = $this->peerRegistery->find($frame->fd);
        if (!$peer) {
            $server->close($frame->fd, true);

            return;
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
