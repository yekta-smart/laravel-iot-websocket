<?php

namespace YektaSmart\IotServer\Websocket;

use Illuminate\Support\ServiceProvider as SupportServiceProvider;
use Swoole\WebSocket\Server;
use SwooleTW\Http\Server\Facades\Server as LaravelSwoole;
use SwooleTW\Http\Table\Facades\SwooleTable as LaravelSwooleTable;
use YektaSmart\IotServer\Contracts\IPeerRegistery;
use YektaSmart\IotServer\Contracts\IPostOffice;
use YektaSmart\IotServer\PostOffice as IotServerPostOffice;
use YektaSmart\IotServer\Websocket\PostOffice;

class ServiceProvider extends SupportServiceProvider
{
    public function register(): void
    {
        $driver = $this->findSwooleDriver();
        switch ($driver) {
            case LaravelSwoole::class:
                $this->registerLaravelSwoole();
                break;
            case 'swoole':
                $this->registerLaravelS();
                break;
        }
    }

    protected function findSwooleDriver(): ?string
    {
        if ($this->app->has(LaravelSwoole::class)) {
            return LaravelSwoole::class;
        }
        if ($this->app->has('swoole')) {
            return 'swoole';
        }

        return null;
    }

    protected function registerLaravelSwoole(): void
    {
        $this->app->bind(IPeerRegistery::class, function (): PeerRegistery {
            $resolver = function (): Server {
                return app()->make(LaravelSwoole::class);
            };
            $table = LaravelSwooleTable::get('peers');

            return new PeerRegistery($resolver, $table);
        }, false);
        
        $this->app->singleton(IPostOffice::class, function() {
            $table = LaravelSwooleTable::get('post');
            return new PostOffice($table);
        });
    }

    protected function registerLaravelS(): void
    {
        $this->app->bind(IPeerRegistery::class, function (): PeerRegistery {
            $resolver = function (): Server {
                return app()->make('swoole');
            };
            $table = app()->make('swoole')->peersTable;

            return new PeerRegistery($resolver, $table);
        }, false);
    }
}
