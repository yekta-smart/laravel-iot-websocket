<?php

namespace YektaSmart\IotServer\Websocket\Concerns;

use Swoole\WebSocket\Server;

trait WorksWithSwoole
{
    /**
     * @var callable
     */
    protected $swooleResolver;

    protected function resolveSwoole(): Server
    {
        return call_user_func($this->swooleResolver);
    }

    public function getSwooleResolver(): ?callable {
        return $this->swooleResolver;
    }
}
