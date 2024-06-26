<?php


declare(strict_types=1);

namespace PHPinnacle\Ridge;

use function Amp\async;

final class Events
{
    /**
     * @var Channel
     */
    private $channel;

    /**
     * @var MessageReceiver
     */
    private $receiver;

    public function __construct(Channel $channel, MessageReceiver $receiver)
    {
        $this->channel = $channel;
        $this->receiver = $receiver;
    }

    public function onAck(callable $listener): self
    {
        $this->onFrame(Protocol\BasicAckFrame::class, $listener);

        return $this;
    }

    public function onNack(callable $listener): self
    {
        $this->onFrame(Protocol\BasicNackFrame::class, $listener);

        return $this;
    }

    public function onReturn(callable $listener): self
    {
        $this->receiver->onMessage(
            function (Message $message) use ($listener) {
                if (!$message->returned) {
                    return;
                }

                async(function () use ($listener, $message) {
                    $listener($message, $this->channel);
                })->catch(function (\Throwable $e) {
                    throw $e;
                });
            }
        );

        return $this;
    }

    /**
     * @psalm-param class-string<Protocol\AbstractFrame> $frame
     */
    private function onFrame(string $frame, callable $callback): void
    {
        $this->receiver->onFrame(
            $frame,
            function (Protocol\AcknowledgmentFrame $frame) use ($callback) {
                async(function () use ($callback, $frame) {
                    $callback($frame->deliveryTag, $frame->multiple, $this->channel);
                })->catch(function (\Throwable $e) {
                    throw $e;
                });
            }
        );
    }
}
