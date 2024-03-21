<?php

declare(strict_types=1);

namespace PHPinnacle\Ridge;

use function Amp\async;

final class Consumer
{
    /**
     * @var Channel
     */
    private $channel;

    /**
     * @var MessageReceiver
     */
    private $receiver;

    /**
     * @var callable[]
     * @psalm-var array<string, callable>
     */
    private $listeners = [];

    /**
     * @var list<Message>
     */
    private array $backprocessQueue = [];

    public function __construct(Channel $channel, MessageReceiver $receiver)
    {
        $this->channel = $channel;
        $this->receiver = $receiver;
    }

    public function start(): void
    {
        $this->receiver->onMessage(
            function (Message $message) {
                if (!$tag = $message->consumerTag) {
                    return;
                }
                async(function () use ($tag, $message) {
                    if (!isset($this->listeners[$tag])) {
                        $this->backprocessQueue[] = $message;
                        return;
                    }
                    $this->listeners[$tag]($message, $this->channel);
                })->catch(function (\Throwable $e) {
                    throw $e;
                });
            }
        );
    }

    public function stop(): void
    {
        $this->listeners = [];
    }

    public function subscribe(string $tag, callable $listener): void
    {
        $this->listeners[$tag] = $listener;
        // New messages can arrive faster (in start()) than we set up a new listener for handling them, and the messages are lost in this case.
        // This code below allows to backprocess already received not processed messages upon adding a listener.
        foreach ($this->backprocessQueue as $i => $message) {
            if ($message->consumerTag === $tag) {
                $listener($message, $this->channel);
                unset($this->backprocessQueue[$i]);
            }
        }
    }

    public function cancel(string $tag): void
    {
        unset($this->listeners[$tag]);
    }
}
