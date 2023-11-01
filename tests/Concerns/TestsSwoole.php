<?php

namespace YektaSmart\IotServer\Websocket\Tests\Concerns;

use Swoole\WebSocket\Server;

trait TestsSwoole
{
    protected $swooleStub;

    public function getSwooleStub()
    {
        if (!$this->swooleStub) {
            /*
             * @var mixed
             */
            $this->swooleStub = $this->createStub(Server::class);
            $this->swooleStub->method('exists')->willReturn(true);
        }

        return $this->swooleStub;
    }

    protected function getSwooleResolver(): callable
    {
        return [$this, 'getSwooleStub'];
    }
}
