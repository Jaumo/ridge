<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Buffer;
use PHPinnacle\Ridge\Constants;

class ChannelOpenFrame extends MethodFrame
{
    /**
     * @var string
     */
    public $outOfBand = '';

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CHANNEL, Constants::METHOD_CHANNEL_OPEN);
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;

        $self->outOfBand = $buffer->consumeString();

        return $self;
    }

    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendString($this->outOfBand);

        return $buffer;
    }
}
