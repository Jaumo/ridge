<?php


namespace PHPinnacle\Ridge\Tests;

use PHPinnacle\Ridge\Client;
use PHPinnacle\Ridge\Config;
use PHPUnit\Framework\TestCase;

abstract class RidgeTest extends TestCase
{
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        if(!$dsn = \getenv('RIDGE_TEST_DSN'))
        {
            self::markTestSkipped('No test dsn! Please set RIDGE_TEST_DSN environment variable.');
        }

        $config = Config::parse($dsn);

        $this->client = new Client($config);
        $this->client->connect();
    }

    protected function tearDown(): void
    {
        if ($this->client->isConnected()) {
            $this->client->disconnect();
        }
        parent::tearDown();
    }
}
