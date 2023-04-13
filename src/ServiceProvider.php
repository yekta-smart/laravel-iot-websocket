<?php

namespace YektaSmart\IotServer\Websocket;

use Illuminate\Support\ServiceProvider as SupportServiceProvider;
use YektaSmart\IotServer\Contracts\IPeerRegistery;

class ServiceProvider extends SupportServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IPeerRegistery::class, PeerRegistery::class, false);
    }
}
