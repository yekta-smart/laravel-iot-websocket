<?php

namespace YektaSmart\IotServer\Websocket;

use YektaSmart\IotServer\Contracts\IEnvelope;
use YektaSmart\IotServer\Contracts\IMessage;
use YektaSmart\IotServer\Contracts\IPeer;
use YektaSmart\IotServer\Contracts\IPostOffice;
use Swoole\Table;

class PostOffice extends SwooleEventEmitter implements IPostOffice
{
    public function __construct(Table $postTable)
    {
        parent::__construct($postTable, "");
    }

    public function send(IPeer $peer, IMessage $content, string|callable|null $cb = null, int|IEnvelope|null $replyTo = null): void
    {
        $envelopeType = $replyTo instanceof IEnvelope ? get_class($replyTo) : $peer->getEnvelopeType();
        if ($replyTo instanceof IEnvelope) {
            $replyTo = $replyTo->getId();
        }
        $e = $content instanceof IEnvelope ? $content : call_user_func([$envelopeType, 'newEnvelope'], $content, $replyTo);

        if (null !== $cb) {
            $this->addListenerForReply($e, $cb, 10);
        }
        $peer->send($e->serializeToString());
    }

    public function receive(IPeer $peer, IEnvelope|string $envelope): void
    {
        if (is_string($envelope)) {
            $envelope = $this->decodeEnvelope($peer, $envelope);
        }
        $content = $envelope->getContent();

        $this->emit(get_class($content), $peer, $content, $envelope);
        if ($envelope->getReplyTo()) {
            $this->emit('replyTo-'.$envelope->getReplyTo(), $peer, $content, $envelope);
        }
    }

    protected function addListenerForReply(IEnvelope $e, string|callable|null $cb, int $timeout): void
    {
        $this->once('replyTo-'.$e->getId(), $cb, time() + $timeout);
    }

    protected function decodeEnvelope(IPeer $peer, string $data): IEnvelope
    {
        $envelopeType = $peer->hasEnvelopeType() ? $peer->getEnvelopeType() : null;
        if (null === $envelopeType) {
            /**
             * @var IEnvelope[]
             */
            $types = app()->tagged(IEnvelope::class);
            foreach ($types as $type) {
                try {
                    $type->mergeFromString($data);
                } catch (\Exception $e) {
                }
                $peer->setEnvelopeType(get_class($type));

                return $type;
            }
            throw new \Exception('decoding error');
        }
        $e = new $envelopeType();
        $e->mergeFromString($data);

        return $e;
    }
}
