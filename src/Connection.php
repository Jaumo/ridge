<?php
declare(strict_types=1);

namespace PHPinnacle\Ridge;

use Amp\DeferredFuture;
use Amp\Socket\ConnectContext;
use Amp\Socket\ConnectException;
use Amp\Socket\DnsSocketConnector;
use Amp\Socket\RetrySocketConnector;
use Amp\Socket\Socket;
use Evenement\EventEmitterTrait;
use PHPinnacle\Ridge\Exception\ConnectionException;
use PHPinnacle\Ridge\Protocol\AbstractFrame;
use Revolt\EventLoop;
use function Amp\async;

final class Connection
{
    use EventEmitterTrait;

    public const EVENT_CLOSE = 'close';

    /**
     * @var string
     */
    private $uri;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var Socket|null
     */
    private $socket;

    private bool $socketClosedExpectedly = false;

    /**
     * @var callable[][][]
     * @psalm-var array<int, array<class-string<AbstractFrame>, array<int, callable>>>
     */
    private $callbacks = [];

    /**
     * @var int
     */
    private $lastWrite = 0;

    /**
     * @var int
     */
    private $lastRead = 0;

    /**
     * @var string|null
     */
    private $heartbeatWatcherId;

    /**
     * @var list<AbstractFrame>
     */
    private array $backprocessQueue = [];

    public function __construct(string $uri)
    {
        $this->uri = $uri;
        $this->parser = new Parser;
    }

    public function connected(): bool
    {
        return $this->socket !== null && $this->socket->isClosed() === false;
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\ConnectionException
     */
    public function write(Buffer $payload): void
    {
        $this->lastWrite = hrtime(true) / 100_000_000;

        if ($this->socket !== null) {
            try {
                $this->socket->write($payload->flush());
                return;
            } catch (\Throwable $throwable) {
                throw ConnectionException::writeFailed($throwable);
            }
        }

        throw ConnectionException::socketClosed();
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\ConnectionException
     */
    public function method(int $channel, Buffer $payload): void
    {
        $this->write((new Buffer)
            ->appendUint8(1)
            ->appendUint16($channel)
            ->appendUint32($payload->size())
            ->append($payload)
            ->appendUint8(206)
        );
    }

    /**
     * @psalm-param class-string<AbstractFrame> $frame
     */
    public function subscribe(int $channel, string $frame, callable $callback): void
    {
        $this->callbacks[$channel][$frame][] = $callback;

        // New frames can arrive faster than we set up a new callback for handling them, and the frames are lost in this case.
        // This code below allows to backprocess already received not processed frames upon adding a callback.
        foreach (array_filter($this->backprocessQueue, static fn (AbstractFrame $f) => $f instanceof $frame) as $frameObj) {
            /**
             * @psalm-var callable(AbstractFrame):bool $c
             */
            foreach ($this->callbacks[$channel][$frame] ?? [] as $i => $c) {
                if ($c($frameObj)) {
                    unset($this->callbacks[$channel][$frame][$i]);
                }
            }
        }

    }

    public function cancel(int $channel): void
    {
        unset($this->callbacks[$channel]);
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\ConnectionException
     */
    public function open(int $timeout, int $maxAttempts, bool $noDelay): void
    {
        $context = new ConnectContext();

        if ($timeout > 0) {
            $context = $context->withConnectTimeout($timeout);
        }

        if ($noDelay) {
            $context = $context->withTcpNoDelay();
        }

        try {
            $this->socket = (new RetrySocketConnector(new DnsSocketConnector(), $maxAttempts > 0 ? $maxAttempts : 3))->connect($this->uri, $context);
        } catch (ConnectException) {
            throw ConnectionException::socketClosed();
        }
        $this->socketClosedExpectedly = false;
        $this->lastRead = hrtime(true) / 1_000_000;

        async(function () {
            if ($this->socket === null) {
                throw ConnectionException::socketClosed();
            }

            while (null !== $chunk = $this->socket->read()) {
                $this->parser->append($chunk);

                while ($frame = $this->parser->parse()) {
                    $class = \get_class($frame);
                    $this->lastRead = hrtime(true) / 1_000_000;

                    $foundCallbackForFrame = false;
                    /**
                     * @psalm-var callable(AbstractFrame):bool $callback
                     */
                    foreach ($this->callbacks[(int)$frame->channel][$class] ?? [] as $i => $callback) {
                        if ($callback($frame)) {
                            unset($this->callbacks[(int)$frame->channel][$class][$i]);
                            $foundCallbackForFrame = true;
                        }
                    }
                    if (!$foundCallbackForFrame) {
                        $this->backprocessQueue[] = $frame;
                    }
                }
            }

            $this->emit(self::EVENT_CLOSE, $this->socketClosedExpectedly ? [] : [Exception\ConnectionException::lostConnection()]);
            $this->socket = null;
        })->catch(function (\Throwable $e) {
            throw $e;
        });
    }

    /**
     * @param int $timeout In milliseconds.
     */
    public function heartbeat(int $timeout): void
    {
        /**
         * Heartbeat interval should be timeout / 2 according to rabbitmq docs
         * @link https://www.rabbitmq.com/heartbeats.html#heartbeats-timeout
         *
         * We run the callback even more often to avoid race conditions if the loop is a bit under pressure
         * otherwise we could miss heartbeats in rare conditions
         */
        $interval = $timeout / 2;
        $this->heartbeatWatcherId = EventLoop::repeat(
            $interval / 3 / 1000,
            function (string $watcherId) use ($interval, $timeout){
                $currentTime = hrtime(true) / 1_000_000;

                if (null !== $this->socket) {
                    $lastWrite = $this->lastWrite ?: $currentTime;

                    $nextHeartbeat = $lastWrite + $interval;

                    if ($currentTime >= $nextHeartbeat) {
                        $this->write((new Buffer)
                            ->appendUint8(8)
                            ->appendUint16(0)
                            ->appendUint32(0)
                            ->appendUint8(206)
                        );
                    }

                    unset($lastWrite, $nextHeartbeat);
                }

                if (
                    0 !== $this->lastRead &&
                    $currentTime > ($this->lastRead + $timeout + 1000)
                )
                {
                    EventLoop::cancel($watcherId);
                }

                unset($currentTime);
            });
    }

    public function close(): void
    {
        $this->callbacks = [];

        if ($this->heartbeatWatcherId !== null) {
            EventLoop::cancel($this->heartbeatWatcherId);

            $this->heartbeatWatcherId = null;
        }

        if ($this->socket !== null) {
            $this->socketClosedExpectedly = true;
            $this->socket->close();
        }
    }
}
