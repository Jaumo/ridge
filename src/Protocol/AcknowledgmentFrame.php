<?php


namespace PHPinnacle\Ridge\Protocol;

abstract class AcknowledgmentFrame extends MethodFrame
{
    /**
     * @var int
     */
    public $deliveryTag = 0;

    /**
     * @var bool
     */
    public $multiple = false;
}
