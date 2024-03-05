<?php


namespace PHPinnacle\Ridge\Tests;

use PHPinnacle\Ridge\Client;
use PHPinnacle\Ridge\Exception\ConnectionException;
use PHPUnit\Framework\TestCase;

class ClientConnectTest extends TestCase
{
    public function testConnectFailure(): void
    {
        $this->expectException(ConnectionException::class);

        $client = Client::create('amqp://127.0.0.2:5673');
        $client->connect();
    }
}
