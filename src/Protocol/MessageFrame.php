<?php


namespace PHPinnacle\Ridge\Protocol;

class MessageFrame extends MethodFrame
{
    /**
     * @var string
     */
    public $exchange;

    /**
     * @var string
     */
    public $routingKey;

    /**
     * @var int
     */
    public $deliveryTag;

    /**
     * @var bool
     */
    public $redelivered = false;
}
