<?php

namespace YektaSmart\IotServer\Websocket\LaravelSwoole;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Support\Facades\Gate;
use YektaSmart\IotServer\Contracts\IDeviceManager;
use YektaSmart\IotServer\Contracts\IPeerRegistery;

class HandShakeHandler
{
    /**
     * @param \Swoole\Http\Request  $request
     * @param \Swoole\Http\Response $response
     */
    public function handle($request, $response): bool
    {
        $socketkey = $request->header['sec-websocket-key'];

        if (0 === preg_match('#^[+/0-9A-Za-z]{21}[AQgw]==$#', $socketkey) || 16 !== strlen(base64_decode($socketkey))) {
            $response->end();

            return false;
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
            $peerRegistery = app(IPeerRegistery::class);

            if (!$peerRegistery->hasDevice($device->getId())) {
                $response->status(504);
                $response->end();

                return false;
            }
        }

        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => base64_encode(sha1($socketkey.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)),
            'Sec-WebSocket-Version' => '13',
        ];

        if (isset($request->header['sec-websocket-protocol'])) {
            $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
        }

        foreach ($headers as $header => $val) {
            $response->header($header, $val);
        }

        $response->status(101);
        $response->end();

        return true;
    }
}
