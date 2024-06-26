<?php
declare(strict_types=1);

namespace PHPinnacle\Ridge;

use Amp\DeferredFuture;
use Amp\Future;
use Evenement\EventEmitterTrait;
use PHPinnacle\Ridge\Exception\ProtocolException;

final class Channel
{
    use EventEmitterTrait;

    public const EVENT_CHANNEL_CLOSED = 'close';

    private const STATE_READY = 1;
    private const STATE_OPEN = 2;
    private const STATE_CLOSING = 3;
    private const STATE_CLOSED = 4;
    private const STATE_ERROR = 5;

    /** Regular AMQP guarantees of published messages delivery.  */
    private const MODE_REGULAR = 1;
    /** Messages are published after 'tx.commit'. */
    private const MODE_TRANSACTIONAL = 2;
    /** Broker sends asynchronously 'basic.ack's for delivered messages. */
    private const MODE_CONFIRM = 3;

    private int $id;

    private Connection $connection;

    private Properties $properties;

    private int $state = self::STATE_READY;

    private int $mode = self::MODE_REGULAR;

    private MessageReceiver $receiver;

    private Consumer $consumer;

    private Events $events;

    private int $deliveryTag = 0;

    private CommandWaitQueue $commandWaitQueue;

    public function __construct(int $id, Connection $connection, Properties $properties)
    {
        $this->id = $id;
        $this->connection = $connection;
        $this->properties = $properties;
        $this->receiver = new MessageReceiver($this, $connection);
        $this->consumer = new Consumer($this, $this->receiver);
        $this->events = new Events($this, $this->receiver);
        $this->commandWaitQueue = new CommandWaitQueue();
    }

    public function id(): int
    {
        return $this->id;
    }

    public function events(): Events
    {
        return $this->events;
    }

    private function assertOpen(): void {
        if ($this->state !== self::STATE_OPEN) {
            throw Exception\ChannelException::notOpen($this->id);
        }
    }

    public function isOpen(): bool {
        return $this->state === self::STATE_OPEN;
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\ChannelException
     */
    public function open(string $outOfBand = ''): void
    {
        if ($this->state !== self::STATE_READY) {
            throw Exception\ChannelException::notReady($this->id);
        }

        $this->connection->write((new Buffer)
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(5 + \strlen($outOfBand))
            ->appendUint16(20)
            ->appendUint16(10)
            ->appendString($outOfBand)
            ->appendUint8(206)
        );

        $this->await(Protocol\ChannelOpenOkFrame::class)->await();

        $this->receiver->start();
        $this->consumer->start();

        $this->state = self::STATE_OPEN;
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\ChannelException
     */
    public function close(int $code = 0, string $reason = ''): void
    {
        if ($this->state === self::STATE_CLOSED) {
            throw Exception\ChannelException::alreadyClosed($this->id);
        }

        if ($this->state === self::STATE_CLOSING) {
            return;
        }

        $this->state = self::STATE_CLOSING;

        $this->receiver->stop();
        $this->consumer->stop();

        $this->connection->write((new Buffer)
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(11 + \strlen($reason))
            ->appendUint16(20)
            ->appendUint16(40)
            ->appendInt16($code)
            ->appendString($reason)
            ->appendInt16(0)
            ->appendInt16(0)
            ->appendUint8(206)
        );

        $this->await(Protocol\ChannelCloseOkFrame::class)->await();

        $this->connection->cancel($this->id);

        $this->state = self::STATE_CLOSED;
        $this->emit(self::EVENT_CHANNEL_CLOSED);
    }

    public function qos(int $prefetchSize = 0, int $prefetchCount = 0, bool $global = false): void
    {
        $this->assertOpen();
        $this->connection->write((new Buffer)
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(11)
            ->appendUint16(60)
            ->appendUint16(10)
            ->appendInt32($prefetchSize)
            ->appendInt16($prefetchCount)
            ->appendBits([$global])
            ->appendUint8(206)
        );

        $this->await(Protocol\BasicQosOkFrame::class)->await();
    }

    public function consume
    (
        callable $callback,
        string $queue = '',
        string $consumerTag = '',
        bool $noLocal = false,
        bool $noAck = false,
        bool $exclusive = false,
        bool $noWait = false,
        array $arguments = []
    ): string {
        $flags = [$noLocal, $noAck, $exclusive, $noWait];

        $this->assertOpen();
        $this->connection->method($this->id, (new Buffer)
            ->appendUint16(60)
            ->appendUint16(20)
            ->appendInt16(0)
            ->appendString($queue)
            ->appendString($consumerTag)
            ->appendBits($flags)
            ->appendTable($arguments)
        );

        if ($noWait === false) {
            /** @var Protocol\BasicConsumeOkFrame $frame */
            $frame = $this->await(Protocol\BasicConsumeOkFrame::class)->await();

            if ('' === $consumerTag) {
                $consumerTag = $frame->consumerTag;
            }
        }

        $this->consumer->subscribe($consumerTag, $callback);

        return $consumerTag;
    }

    public function cancel(string $consumerTag, bool $noWait = false): void
    {
        $this->assertOpen();
        $this->connection->write((new Buffer)
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(6 + \strlen($consumerTag))
            ->appendUint16(60)
            ->appendUint16(30)
            ->appendString($consumerTag)
            ->appendBits([$noWait])
            ->appendUint8(206)
        );

        if ($noWait === false) {
            $this->await(Protocol\BasicCancelOkFrame::class)->await();
        }

        $this->consumer->cancel($consumerTag);
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\ProtocolException
     */
    public function ack(Message $message, bool $multiple = false): void
    {
        $this->assertOpen();
        if ($message->deliveryTag === null) {
            throw ProtocolException::unsupportedDeliveryTag();
        }

        $this->connection->write((new Buffer)
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(13)
            ->appendUint16(60)
            ->appendUint16(80)
            ->appendInt64($message->deliveryTag)
            ->appendBits([$multiple])
            ->appendUint8(206)
        );
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\ProtocolException
     */
    public function nack(Message $message, bool $multiple = false, bool $requeue = true): void
    {
        $this->assertOpen();
        if ($message->deliveryTag === null) {
            throw ProtocolException::unsupportedDeliveryTag();
        }

        $this->connection->write((new Buffer)
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(13)
            ->appendUint16(60)
            ->appendUint16(120)
            ->appendInt64($message->deliveryTag)
            ->appendBits([$multiple, $requeue])
            ->appendUint8(206)
        );
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\ProtocolException
     */
    public function reject(Message $message, bool $requeue = true): void
    {
        if ($message->deliveryTag === null) {
            throw ProtocolException::unsupportedDeliveryTag();
        }

        $this->connection->write((new Buffer)
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(13)
            ->appendUint16(60)
            ->appendUint16(90)
            ->appendInt64($message->deliveryTag)
            ->appendBits([$requeue])
            ->appendUint8(206)
        );
    }

    public function recover(bool $requeue = false): void
    {
        $this->assertOpen();
        $this->connection->write((new Buffer)
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(5)
            ->appendUint16(60)
            ->appendUint16(110)
            ->appendBits([$requeue])
            ->appendUint8(206)
        );

        $this->await(Protocol\BasicRecoverOkFrame::class)->await();
    }

    public function get(string $queue = '', bool $noAck = false): ?Message
    {
        static $getting = false;

        $this->assertOpen();
        if ($getting) {
            throw Exception\ChannelException::getInProgress();
        }

        $getting = true;
        
        $this->connection->write((new Buffer)
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(8 + \strlen($queue))
            ->appendUint16(60)
            ->appendUint16(70)
            ->appendInt16(0)
            ->appendString($queue)
            ->appendBits([$noAck])
            ->appendUint8(206)
        );

        /** @var Protocol\BasicGetOkFrame|Protocol\BasicGetEmptyFrame $frame */
        $frame = Future\awaitAny([
            $this->await(Protocol\BasicGetOkFrame::class),
            $this->await(Protocol\BasicGetEmptyFrame::class)
        ]);

        if ($frame instanceof Protocol\BasicGetEmptyFrame) {
            $getting = false;

            return null;
        }

        /** @var Protocol\ContentHeaderFrame $header */
        $header = $this->await(Protocol\ContentHeaderFrame::class)->await();

        $buffer = new Buffer;
        $remaining = $header->bodySize;

        while ($remaining > 0) {
            /** @var Protocol\ContentBodyFrame $body */
            $body = $this->await(Protocol\ContentBodyFrame::class)->await();

            $buffer->append((string)$body->payload);

            $remaining -= (int)$body->size;

            if ($remaining < 0) {
                $this->state = self::STATE_ERROR;
                throw Exception\ChannelException::bodyOverflow($remaining);
            }
        }
        
        $getting = false;

        return new Message(
            $buffer->flush(),
            $frame->exchange,
            $frame->routingKey,
            null,
            $frame->deliveryTag,
            $frame->redelivered,
            false,
            $header->toArray()
        );
    }

    public function publish
    (
        string $body,
        string $exchange = '',
        string $routingKey = '',
        array $headers = [],
        bool $mandatory = false,
        bool $immediate = false
    ): ?int {
        $this->assertOpen();
        $this->doPublish($body, $exchange, $routingKey, $headers, $mandatory, $immediate);

        return $this->mode === self::MODE_CONFIRM ? ++$this->deliveryTag : null;
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\ChannelException
     */
    public function txSelect(): void
    {
        $this->assertOpen();
        if ($this->mode !== self::MODE_REGULAR) {
            throw Exception\ChannelException::notRegularFor("transactional");
        }

        $this->connection->write((new Buffer)
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(4)
            ->appendUint16(90)
            ->appendUint16(10)
            ->appendUint8(206)
        );

        $this->await(Protocol\TxSelectOkFrame::class)->await();

        $this->mode = self::MODE_TRANSACTIONAL;
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\ChannelException
     */
    public function txCommit(): void
    {
        $this->assertOpen();
        if ($this->mode !== self::MODE_TRANSACTIONAL) {
            throw Exception\ChannelException::notTransactional();
        }

        $this->connection->write((new Buffer)
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(4)
            ->appendUint16(90)
            ->appendUint16(20)
            ->appendUint8(206)
        );

        $this->await(Protocol\TxCommitOkFrame::class)->await();
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\ChannelException
     */
    public function txRollback(): void
    {
        $this->assertOpen();
        if ($this->mode !== self::MODE_TRANSACTIONAL) {
            throw Exception\ChannelException::notTransactional();
        }

        $this->connection->write((new Buffer)
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(4)
            ->appendUint16(90)
            ->appendUint16(30)
            ->appendUint8(206)
        );

        $this->await(Protocol\TxRollbackOkFrame::class)->await();
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\ChannelException
     */
    public function confirmSelect(bool $noWait = false): void
    {
        $this->assertOpen();
        if ($this->mode !== self::MODE_REGULAR) {
            throw Exception\ChannelException::notRegularFor("confirm");
        }

        $this->connection->write((new Buffer)
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(5)
            ->appendUint16(85)
            ->appendUint16(10)
            ->appendBits([$noWait])
            ->appendUint8(206)
        );

        if ($noWait === false) {
            $this->await(Protocol\ConfirmSelectOkFrame::class)->await();
        }

        $this->mode = self::MODE_CONFIRM;
        $this->deliveryTag = 0;
    }

    public function queueDeclare
    (
        string $queue = '',
        bool $passive = false,
        bool $durable = false,
        bool $exclusive = false,
        bool $autoDelete = false,
        bool $noWait = false,
        array $arguments = []
    ): ?Queue {
        $flags = [$passive, $durable, $exclusive, $autoDelete, $noWait];

        $this->assertOpen();
        $this->connection->method($this->id, (new Buffer)
            ->appendUint16(50)
            ->appendUint16(10)
            ->appendInt16(0)
            ->appendString($queue)
            ->appendBits($flags)
            ->appendTable($arguments)
        );

        if ($noWait) {
            return null;
        }

        $frame = $this->await(Protocol\QueueDeclareOkFrame::class)->await();

        return new Queue($frame->queue, $frame->messageCount, $frame->consumerCount);
    }


    public function queueBind
    (
        string $queue = '',
        string $exchange = '',
        string $routingKey = '',
        bool $noWait = false,
        array $arguments = []
    ): void {
        $this->assertOpen();
        $this->connection->method($this->id, (new Buffer)
            ->appendUint16(50)
            ->appendUint16(20)
            ->appendInt16(0)
            ->appendString($queue)
            ->appendString($exchange)
            ->appendString($routingKey)
            ->appendBits([$noWait])
            ->appendTable($arguments)
        );

        if ($noWait) {
            return;
        }

        $this->await(Protocol\QueueBindOkFrame::class)->await();
    }

    public function queueUnbind
    (
        string $queue = '',
        string $exchange = '',
        string $routingKey = '',
        bool $noWait = false,
        array $arguments = []
    ): void {
        $this->assertOpen();
        $this->connection->method($this->id, (new Buffer)
            ->appendUint16(50)
            ->appendUint16(50)
            ->appendInt16(0)
            ->appendString($queue)
            ->appendString($exchange)
            ->appendString($routingKey)
            ->appendTable($arguments)
        );

        if ($noWait) {
            return;
        }

        $this->await(Protocol\QueueUnbindOkFrame::class)->await();
    }

    public function queuePurge(string $queue = '', bool $noWait = false): int
    {
        $this->assertOpen();
        $this->connection->write((new Buffer)
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(8 + \strlen($queue))
            ->appendUint16(50)
            ->appendUint16(30)
            ->appendInt16(0)
            ->appendString($queue)
            ->appendBits([$noWait])
            ->appendUint8(206)
        );

        if ($noWait) {
            return 0;
        }

        /** @var Protocol\QueuePurgeOkFrame $frame */
        $frame = $this->await(Protocol\QueuePurgeOkFrame::class)->await();

        return $frame->messageCount;
    }

    public function queueDelete
    (
        string $queue = '',
        bool $ifUnused = false,
        bool $ifEmpty = false,
        bool $noWait = false
    ): int {
        $flags = [$ifUnused, $ifEmpty, $noWait];

        $this->assertOpen();
        $this->connection->write((new Buffer)
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(8 + strlen($queue))
            ->appendUint16(50)
            ->appendUint16(40)
            ->appendInt16(0)
            ->appendString($queue)
            ->appendBits($flags)
            ->appendUint8(206)
        );

        if ($noWait) {
            return 0;
        }

        /** @var Protocol\QueueDeleteOkFrame $frame */
        $frame = $this->await(Protocol\QueueDeleteOkFrame::class)->await();

        return $frame->messageCount;
    }

    public function exchangeDeclare
    (
        string $exchange,
        string $exchangeType = 'direct',
        bool $passive = false,
        bool $durable = false,
        bool $autoDelete = false,
        bool $internal = false,
        bool $noWait = false,
        array $arguments = []
    ): void {
        $flags = [$passive, $durable, $autoDelete, $internal, $noWait];

        $this->assertOpen();
        $this->connection->method($this->id, (new Buffer)
            ->appendUint16(40)
            ->appendUint16(10)
            ->appendInt16(0)
            ->appendString($exchange)
            ->appendString($exchangeType)
            ->appendBits($flags)
            ->appendTable($arguments)
        );

        if ($noWait) {
            return;
        }

        $this->await(Protocol\ExchangeDeclareOkFrame::class)->await();
    }

    public function exchangeBind
    (
        string $destination,
        string $source,
        string $routingKey = '',
        bool $noWait = false,
        array $arguments = []
    ): void {
        $this->assertOpen();
        $this->connection->method($this->id, (new Buffer)
            ->appendUint16(40)
            ->appendUint16(30)
            ->appendInt16(0)
            ->appendString($destination)
            ->appendString($source)
            ->appendString($routingKey)
            ->appendBits([$noWait])
            ->appendTable($arguments)
        );

        if ($noWait) {
            return;
        }

        $this->await(Protocol\ExchangeBindOkFrame::class)->await();
    }

    public function exchangeUnbind
    (
        string $destination,
        string $source,
        string $routingKey = '',
        bool $noWait = false,
        array $arguments = []
    ): void {
        $this->assertOpen();
        $this->connection->method($this->id, (new Buffer)
            ->appendUint16(40)
            ->appendUint16(40)
            ->appendInt16(0)
            ->appendString($destination)
            ->appendString($source)
            ->appendString($routingKey)
            ->appendBits([$noWait])
            ->appendTable($arguments)
        );

        if ($noWait) {
            return;
        }

        $this->await(Protocol\ExchangeUnbindOkFrame::class)->await();
    }

    public function exchangeDelete(string $exchange, bool $unused = false, bool $noWait = false): void
    {
        $this->assertOpen();
        $this->connection->write((new Buffer)
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(8 + \strlen($exchange))
            ->appendUint16(40)
            ->appendUint16(20)
            ->appendInt16(0)
            ->appendString($exchange)
            ->appendBits([$unused, $noWait])
            ->appendUint8(206)
        );

        if ($noWait) {
            return;
        }

        $this->await(Protocol\ExchangeDeleteOkFrame::class)->await();
    }

    public function doPublish
    (
        string $body,
        string $exchange = '',
        string $routingKey = '',
        array $headers = [],
        bool $mandatory = false,
        bool $immediate = false
    ): void {
        $flags = 0;
        $contentType = '';
        $contentEncoding = '';
        $type = '';
        $replyTo = '';
        $expiration = '';
        $messageId = '';
        $correlationId = '';
        $userId = '';
        $appId = '';
        $clusterId = '';

        $deliveryMode = null;
        $priority = null;
        $timestamp = null;

        $headersBuffer = null;

        $buffer = new Buffer;
        $buffer
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(9 + \strlen($exchange) + \strlen($routingKey))
            ->appendUint16(60)
            ->appendUint16(40)
            ->appendInt16(0)
            ->appendString($exchange)
            ->appendString($routingKey)
            ->appendBits([$mandatory, $immediate])
            ->appendUint8(206);

        $size = 14;

        if (isset($headers['content-type'])) {
            $flags |= 32768;
            $contentType = (string)$headers['content-type'];
            $size += 1 + \strlen($contentType);

            unset($headers['content-type']);
        }

        if (isset($headers['content-encoding'])) {
            $flags |= 16384;
            $contentEncoding = (string)$headers['content-encoding'];
            $size += 1 + \strlen($contentEncoding);

            unset($headers['content-encoding']);
        }

        if (isset($headers['delivery-mode'])) {
            $flags |= 4096;
            $deliveryMode = (int)$headers['delivery-mode'];
            ++$size;

            unset($headers['delivery-mode']);
        }

        if (isset($headers['priority'])) {
            $flags |= 2048;
            $priority = (int)$headers['priority'];
            ++$size;

            unset($headers['priority']);
        }

        if (isset($headers['correlation-id'])) {
            $flags |= 1024;
            $correlationId = (string)$headers['correlation-id'];
            $size += 1 + \strlen($correlationId);

            unset($headers['correlation-id']);
        }

        if (isset($headers['reply-to'])) {
            $flags |= 512;
            $replyTo = (string)$headers['reply-to'];
            $size += 1 + \strlen($replyTo);

            unset($headers['reply-to']);
        }

        if (isset($headers['expiration'])) {
            $flags |= 256;
            $expiration = (string)$headers['expiration'];
            $size += 1 + \strlen($expiration);

            unset($headers['expiration']);
        }

        if (isset($headers['message-id'])) {
            $flags |= 128;
            $messageId = (string)$headers['message-id'];
            $size += 1 + \strlen($messageId);

            unset($headers['message-id']);
        }

        if (isset($headers['timestamp'])) {
            $flags |= 64;
            $timestamp = (int)$headers['timestamp'];
            $size += 8;

            unset($headers['timestamp']);
        }

        if (isset($headers['type'])) {
            $flags |= 32;
            $type = (string)$headers['type'];
            $size += 1 + \strlen($type);

            unset($headers['type']);
        }

        if (isset($headers['user-id'])) {
            $flags |= 16;
            $userId = (string)$headers['user-id'];
            $size += 1 + \strlen($userId);

            unset($headers['user-id']);
        }

        if (isset($headers['app-id'])) {
            $flags |= 8;
            $appId = (string)$headers['app-id'];
            $size += 1 + \strlen($appId);

            unset($headers['app-id']);
        }

        if (isset($headers['cluster-id'])) {
            $flags |= 4;
            $clusterId = (string)$headers['cluster-id'];
            $size += 1 + \strlen($clusterId);

            unset($headers['cluster-id']);
        }

        if (!empty($headers)) {
            $flags |= 8192;
            $headersBuffer = new Buffer;
            $headersBuffer->appendTable($headers);
            $size += $headersBuffer->size();
        }

        $buffer
            ->appendUint8(2)
            ->appendUint16($this->id)
            ->appendUint32($size)
            ->appendUint16(60)
            ->appendUint16(0)
            ->appendUint64(\strlen($body))
            ->appendUint16($flags);

        if ($flags & 32768) {
            $buffer->appendString($contentType);
        }

        if ($flags & 16384) {
            $buffer->appendString($contentEncoding);
        }

        if ($flags & 8192 && $headersBuffer !== null) {
            $buffer->append($headersBuffer);
        }

        if ($flags & 4096 && $deliveryMode !== null) {
            $buffer->appendUint8($deliveryMode);
        }

        if ($flags & 2048 && $priority !== null) {
            $buffer->appendUint8($priority);
        }

        if ($flags & 1024) {
            $buffer->appendString($correlationId);
        }

        if ($flags & 512) {
            $buffer->appendString($replyTo);
        }

        if ($flags & 256) {
            $buffer->appendString($expiration);
        }

        if ($flags & 128) {
            $buffer->appendString($messageId);
        }

        if ($flags & 64 && $timestamp !== null) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $buffer->appendTimestamp(new \DateTimeImmutable(\sprintf('@%s', $timestamp)));
        }

        if ($flags & 32) {
            $buffer->appendString($type);
        }

        if ($flags & 16) {
            $buffer->appendString($userId);
        }

        if ($flags & 8) {
            $buffer->appendString($appId);
        }

        if ($flags & 4) {
            $buffer->appendString($clusterId);
        }

        $buffer->appendUint8(206);

        if (!empty($body)) {
            /* @phpstan-ignore-next-line */
            $chunks = \str_split($body, $this->properties->maxFrame());

            if ($chunks !== false) {
                foreach ($chunks as $chunk) {
                    $buffer
                        ->appendUint8(3)
                        ->appendUint16($this->id)
                        ->appendUint32(\strlen($chunk))
                        ->append($chunk)
                        ->appendUint8(206);
                }
            }
        }

        $this->connection->write($buffer);
    }

    /**
     * @template T of Protocol\AbstractFrame
     * @psalm-param class-string<T> $frame
     * @psalm-return Future<T>
     */
    private function await(string $frame): Future
    {
        $deferred = new DeferredFuture();
        $this->commandWaitQueue->add($deferred);

        $this->connection->subscribe(
            $this->id,
            $frame,
            static function (Protocol\AbstractFrame $frame) use ($deferred) {
                /** @psalm-var T $frame */
                $deferred->complete($frame);

                return true;
            }
        );

        return $deferred->getFuture();
    }

    /**
     * Channel was closed by server
     */
    public function forceClose(\Throwable $exception): void
    {
        if ($this->state !== self::STATE_CLOSED) {
            $this->state = self::STATE_CLOSED;
            $this->commandWaitQueue->cancel($exception);
            $this->emit(self::EVENT_CHANNEL_CLOSED, [$exception]);
        }
    }
}
