<?php

namespace YektaSmart\IotServer\Websocket\Tests;

use dnj\AAA\ServiceProvider as AAAServiceProvider;
use dnj\ErrorTracker\Laravel\Server\ServiceProvider as ErrorTrackerServerServiceProvider;
use dnj\UserLogger\ServiceProvider as UserLoggerServiceProvider;
use Hhxsv5\LaravelS\Illuminate\LaravelSServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use YektaSmart\IotServer\ServiceProvider as IotServerServiceProvider;
use YektaSmart\IotServer\Websocket\ServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [
            UserLoggerServiceProvider::class,
            AAAServiceProvider::class,
            ErrorTrackerServerServiceProvider::class,
            LaravelSServiceProvider::class,
            IotServerServiceProvider::class,
            ServiceProvider::class,
        ];
    }
}
