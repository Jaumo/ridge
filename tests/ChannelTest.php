<?php


namespace PHPinnacle\Ridge\Tests;

use Amp\DeferredFuture;
use PHPinnacle\Ridge\Channel;
use PHPinnacle\Ridge\Exception;
use PHPinnacle\Ridge\Message;
use PHPinnacle\Ridge\Queue;
use Revolt\EventLoop;
use function Amp\async;
use function Amp\delay;
use function Amp\Future\await;

class ChannelTest extends RidgeTest
{
    public function testOpenNotReadyChannel(): void
    {
        $this->expectException(Exception\ChannelException::class);

        $channel = $this->client->channel();

        $channel->open();

        $this->client->disconnect();
    }

    public function testClose(): void
    {
        $channel = $this->client->channel();

        $channel->close();

        $this->client->disconnect();
    }

    public function testCloseAlreadyClosedChannel(): void
    {
        $this->expectException(Exception\ChannelException::class);

        $channel = $this->client->channel();

        try {
            $channel->close();
            $channel->close();
        } finally {
            $this->client->disconnect();
        }
    }

    public function testExchangeDeclare(): void
    {
        $channel = $this->client->channel();

        $channel->exchangeDeclare('test_exchange', 'direct', false, false, true);

        $this->client->disconnect();
    }

    public function testExchangeDelete(): void
    {
        
        $channel = $this->client->channel();

        $channel->exchangeDeclare('test_exchange_no_ad', 'direct');

        $channel->exchangeDelete('test_exchange_no_ad');
        $this->client->disconnect();
    }

    public function testQueueDeclare(): void
    {
        $queueName = 'test_queue_'.random_int(0, 999_999);
        $channel = $this->client->channel();

        $queue = $channel->queueDeclare($queueName, false, false, false, true);

        self::assertInstanceOf(Queue::class, $queue);
        self::assertSame($queueName, $queue->name());
        self::assertSame(0, $queue->messages());
        self::assertSame(0, $queue->consumers());

        $this->client->disconnect();
    }

    public function testQueueBind(): void
    {
        $channel = $this->client->channel();

        $channel->exchangeDeclare('test_exchange', 'direct', false, false, true);
        $channel->queueDeclare('test_queue', false, false, false, true);

        $channel->queueBind('test_queue', 'test_exchange');

        $this->client->disconnect();
    }

    public function testQueueUnbind(): void
    {
        
        $channel = $this->client->channel();

        $channel->exchangeDeclare('test_exchange', 'direct', false, false, true);
        $channel->queueDeclare('test_queue', false, false, false, true);
        $channel->queueBind('test_queue', 'test_exchange');

        $channel->queueUnbind('test_queue', 'test_exchange');

        $this->client->disconnect();
    }

    public function testQueuePurge(): void
    {
        $channel = $this->client->channel();

        $queue = 'test_queue_'.random_int(0, 999_999);

        $channel->queueDeclare($queue, false, false, false, true);
        $channel->publish('test1', '', $queue);
        $channel->publish('test2', '', $queue);
        
        delay(1);

        $messages = $channel->queuePurge($queue);

        self::assertEquals(2, $messages);

        $this->client->disconnect();
    }

    public function testQueueDelete(): void
    {

        $channel = $this->client->channel();

        $channel->queueDeclare('test_queue_no_ad');
        $channel->publish('test', '', 'test_queue_no_ad');

        $messages = $channel->queueDelete('test_queue_no_ad');

        self::assertEquals(1, $messages);

        $this->client->disconnect();
    }

    public function testPublish(): void
    {
        
        $channel = $this->client->channel();

        $result = $channel->publish('test publish');

        self::assertNull($result);

        $this->client->disconnect();
    }

    public function testMandatoryPublish(): void
    {

        $channel = $this->client->channel();

        $deferred = new DeferredFuture();
        $watcher  = EventLoop::delay(100, function () use ($deferred) {
            $deferred->complete(false);
        });

        $channel->events()->onReturn(function (Message $message) use ($deferred, $watcher) {
            self::assertSame($message->content, '.');
            self::assertSame($message->exchange, '');
            self::assertSame($message->routingKey, '404');
            self::assertSame($message->headers, []);
            self::assertNull($message->consumerTag);
            self::assertNull($message->deliveryTag);
            self::assertFalse($message->redelivered);
            self::assertTrue($message->returned);

            EventLoop::cancel($watcher);

            $deferred->complete(true);
        });

        $channel->publish('.', '', '404', [], true);

        self::assertTrue($deferred->getFuture()->await(), 'Mandatory return event not received!');

        $this->client->disconnect();
    }

    public function testImmediatePublish()
    {
        $properties = $this->client->properties();

        // RabbitMQ 3 doesn't support "immediate" publish flag.
        if ($properties->product() === 'RabbitMQ' && version_compare($properties->version(), '3.0', '>')) {
            $this->client->disconnect();

            return;
        }

        
        $channel = $this->client->channel();

        $deferred = new DeferredFuture();
        $watcher  = EventLoop::delay(100, function () use ($deferred) {
            $deferred->complete(false);
        });

        $channel->events()->onReturn(function (Message $message) use ($deferred, $watcher) {
            self::assertTrue($message->returned);

            EventLoop::cancel($watcher);

            $deferred->complete(true);
        });

        $channel->queueDeclare('test_queue', false, false, false, true);
        $channel->publish('.', '', 'test_queue', [], false, true);

        self::assertTrue($deferred->getFuture()->await(), 'Immediate return event not received!');

        $this->client->disconnect();
    }

    public function testConsume(): void
    {
        
        $channel = $this->client->channel();

        $channel->queueDeclare('test_queue', false, false, false, true);
        $channel->publish('hi', '', 'test_queue');
    
        $tag = $channel->consume(function (Message $message) use (&$tag) {
            self::assertEquals('hi', $message->content);
            self::assertEquals($tag, $message->consumerTag);

            $this->client->disconnect();
        }, 'test_queue', false, true);
    }

    public function testCancel(): void
    {
        
        $channel = $this->client->channel();

        $channel->queueDeclare('test_queue', false, false, false, true);
        $channel->publish('hi', '', 'test_queue');

        $tag = $channel->consume(function (Message $message) {
        }, 'test_queue', false, true);

        $channel->cancel($tag);

        $this->client->disconnect();
    }

    public function testHeaders(): void
    {
        
        $channel = $this->client->channel();

        $channel->queueDeclare('test_queue', false, false, false, true);
        $channel->publish('<b>hi html</b>', '', 'test_queue', [
            'content-type' => 'text/html',
            'custom' => 'value',
        ]);

        $channel->consume(function (Message $message) {
            self::assertEquals('text/html', $message->header('content-type'));
            self::assertEquals('value', $message->header('custom'));
            self::assertEquals('<b>hi html</b>', $message->content);

            $this->client->disconnect();
        }, 'test_queue', false, true);
    }

    public function testGet(): void
    {
        $queue = 'test_queue_'.random_int(0, 999_999);
        $channel = $this->client->channel();

        $channel->queueDeclare($queue, false, false, false, true);

        $channel->publish('.', '', $queue);

        /** @var Message $message1 */
        $message1 = $channel->get($queue, true);

        self::assertNotNull($message1);
        self::assertInstanceOf(Message::class, $message1);
        self::assertEquals('', $message1->exchange);
        self::assertEquals('.', $message1->content);
        self::assertEquals($queue, $message1->routingKey);
        self::assertEquals(1, $message1->deliveryTag);
        self::assertNull($message1->consumerTag);
        self::assertFalse($message1->redelivered);
        self::assertIsArray($message1->headers);

        self::assertNull($channel->get($queue, true));

        $channel->publish('..', '', $queue);

        delay(1);

        /** @var Message $message2 */
        $message2 = $channel->get($queue);

        self::assertNotNull($message2);
        self::assertEquals(2, $message2->deliveryTag);
        self::assertFalse($message2->redelivered);

        $this->client->disconnect();

        $this->client->connect();


        $channel = $this->client->channel();

        /** @var Message $message3 */
        $message3 = $channel->get($queue);

        self::assertNotNull($message3);
        self::assertInstanceOf(Message::class, $message3);
        self::assertEquals('', $message3->exchange);
        self::assertEquals('..', $message3->content);
        self::assertTrue($message3->redelivered);

        $channel->ack($message3);

        $this->client->disconnect();
    }

    public function testAck(): void
    {
        $channel = $this->client->channel();

        $queue = 'test_queue_'.random_int(0, 999_999);

        $channel->queueDeclare($queue, false, false, false, true);
        $channel->publish('test message', '', $queue);

        delay(1);

        /** @var Message $message */
        $message = $channel->get($queue);

        $channel->ack($message);

        $this->client->disconnect();
    }

    public function testNack(): void
    {
        
        $channel = $this->client->channel();

        $channel->queueDeclare('test_queue', false, false, false, true);
        $channel->publish('.', '', 'test_queue');

        delay(1);

        /** @var Message $message */
        $message = $channel->get('test_queue');

        self::assertNotNull($message);
        self::assertFalse($message->redelivered);

        $channel->nack($message);

        /** @var Message $message */
        $message = $channel->get('test_queue');

        self::assertNotNull($message);
        self::assertTrue($message->redelivered);

        $channel->nack($message, false, false);

        self::assertNull($channel->get('test_queue'));

        $this->client->disconnect();
    }

    public function testReject(): void
    {
        $queue = 'test_queue_'.random_int(0, 999_999);

        $channel = $this->client->channel();

        $channel->queueDeclare($queue, false, false, false, true);
        $channel->publish('.', '', $queue);

        delay(1);

        /** @var Message $message */
        $message = $channel->get($queue);

        self::assertNotNull($message);
        self::assertFalse($message->redelivered);

        $channel->reject($message);

        delay(1);

        /** @var Message $message */
        $message = $channel->get($queue);

        self::assertNotNull($message);
        self::assertTrue($message->redelivered);

        $channel->reject($message, false);

        self::assertNull($channel->get($queue));

        $this->client->disconnect();
    }

    public function testRecover(): void
    {
        
        $channel = $this->client->channel();

        $channel->queueDeclare('test_queue', false, false, false, true);
        $channel->publish('.', '', 'test_queue');

        /** @var Message $message */
        $message = $channel->get('test_queue');

        self::assertNotNull($message);
        self::assertFalse($message->redelivered);

        $channel->recover(true);

        /** @var Message $message */
        $message = $channel->get('test_queue');

        self::assertNotNull($message);
        self::assertTrue($message->redelivered);

        $channel->ack($message);

        $this->client->disconnect();
    }

    public function testBigMessage(): void
    {
        $queue = 'test_queue_'.random_int(0, 999_999);
        $channel = $this->client->channel();

        $channel->queueDeclare($queue, false, false, false, true);

        $body = \str_repeat('a', 65528); // 1 byte beyond the limit of a chunk

        $channel->publish($body, '', $queue);

        delay(1);

        $channel->consume(function (Message $message, Channel $channel) use ($body) {
            self::assertEquals(\strlen($body), \strlen($message->content));

            $channel->ack($message);
            $this->client->disconnect();
        }, $queue);
    }

    public function testGetDouble(): void
    {
        $this->expectException(Exception\ChannelException::class);

        
        $channel = $this->client->channel();

        $channel->queueDeclare('get_test_double', false, false, false, true);
        $channel->publish('.', '', 'get_test_double');

        try {
            await([
                async(fn () => $channel->get('get_test_double')),
                async(fn () => $channel->get('get_test_double')),
            ]);

        } finally {
            $channel->queueDelete('get_test_double');

            $this->client->disconnect();
        }
    }

    public function testEmptyMessage(): void
    {
        
        $channel = $this->client->channel();

        $channel->queueDeclare('empty_body_message_test', false, false, false, true);
        $channel->publish('', '', 'empty_body_message_test');

        delay(1);

        /** @var Message $message */
        $message = $channel->get('empty_body_message_test', true);

        self::assertNotNull($message);
        self::assertEquals('', $message->content);

        $count = 0;

        $channel->consume(function (Message $message, Channel $channel) use (&$count) {
            self::assertEmpty($message->content);

            $channel->ack($message);

            if (++$count === 2) {
                $this->client->disconnect();
            }
        }, 'empty_body_message_test');

        $channel->publish('', '', 'empty_body_message_test');
        $channel->publish('', '', 'empty_body_message_test');
    }

    public function testTxs(): void
    {
        
        $channel = $this->client->channel();

        $channel->queueDeclare('tx_test', false, false, false, true);

        $channel->txSelect();
        $channel->publish('.', '', 'tx_test');
        $channel->txCommit();

        /** @var Message $message */
        $message = $channel->get('tx_test', true);

        self::assertNotNull($message);
        self::assertInstanceOf(Message::class, $message);
        self::assertEquals('.', $message->content);

        $channel->publish('..', '', 'tx_test');
        $channel->txRollback();

        $nothing = $channel->get('tx_test', true);

        self::assertNull($nothing);

        $this->client->disconnect();
    }

    public function testTxSelectCannotBeCalledMultipleTimes(): void
    {
        $this->expectException(Exception\ChannelException::class);

        
        $channel = $this->client->channel();

        try {
            $channel->txSelect();
            $channel->txSelect();
        } finally {
            $this->client->disconnect();
        }
    }

    public function testTxCommitCannotBeCalledUnderNotTransactionMode(): void
    {
        $this->expectException(Exception\ChannelException::class);

        
        $channel = $this->client->channel();

        try {
            $channel->txCommit();
        } finally {
            $this->client->disconnect();
        }
    }

    public function testTxRollbackCannotBeCalledUnderNotTransactionMode(): void
    {
        $this->expectException(Exception\ChannelException::class);

        
        $channel = $this->client->channel();

        try {
            $channel->txRollback();
        } finally {
            $this->client->disconnect();
        }
    }

    public function testConfirmMode(): void
    {
        
        $channel = $this->client->channel();
        $channel->events()->onAck(function (int $deliveryTag, bool $multiple) {
            self::assertEquals($deliveryTag, 1);
            self::assertFalse($multiple);
        });

        $channel->confirmSelect();

        $deliveryTag = $channel->publish('.');

        self::assertEquals($deliveryTag, 1);

        $this->client->disconnect();
    }
}
