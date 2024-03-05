<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Buffer;
use PHPinnacle\Ridge\Constants;

class BasicCancelFrame extends MethodFrame
{
    /**
     * @var string
     */
    public $consumerTag;

    /**
     * @var bool
     */
    public $nowait = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_CANCEL);
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;
        $self->consumerTag = $buffer->consumeString();
        [$self->nowait] = $buffer->consumeBits(1);

        return $self;
    }

    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendString($this->consumerTag);
        $buffer->appendBits([$this->nowait]);

        return $buffer;
    }
}
