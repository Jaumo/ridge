<?php


namespace PHPinnacle\Ridge\Tests;

use PHPinnacle\Ridge\Channel;
use PHPinnacle\Ridge\Message;

class ClientTest extends RidgeTest
{
    public function testOpenMultipleChannel(): void
    {
        $channel1 = $this->client->channel();
        $channel2 = $this->client->channel();

        self::assertNotEquals($channel1->id(), $channel2->id());

        $channel3 = $this->client->channel();

        self::assertNotEquals($channel1->id(), $channel3->id());
        self::assertNotEquals($channel2->id(), $channel3->id());

        $this->client->disconnect();
    }

    public function testDisconnectWithBufferedMessages(): void
    {

        $channel = $this->client->channel();
        $count = 0;

        $channel->qos(0, 1000);
        $channel->queueDeclare('disconnect_test', false, false, false, true);
        $channel->consume(function (Message $message, Channel $channel) use (&$count) {
            $channel->ack($message);

            self::assertEquals(1, ++$count);

            $this->client->disconnect();
        }, 'disconnect_test');

        $channel->publish('.', '', 'disconnect_test');
        $channel->publish('.', '', 'disconnect_test');
        $channel->publish('.', '', 'disconnect_test');
    }
}
