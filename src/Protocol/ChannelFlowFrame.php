<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Buffer;
use PHPinnacle\Ridge\Constants;

class ChannelFlowFrame extends MethodFrame
{
    /**
     * @var bool
     */
    public $active;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CHANNEL, Constants::METHOD_CHANNEL_FLOW);
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;
        [$self->active] = $buffer->consumeBits(1);

        return $self;
    }

    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendBits([$this->active]);

        return $buffer;
    }
}
