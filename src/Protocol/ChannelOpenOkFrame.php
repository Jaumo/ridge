<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Buffer;
use PHPinnacle\Ridge\Constants;

class ChannelOpenOkFrame extends MethodFrame
{
    /**
     * @var string
     */
    public $channelId = '';

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CHANNEL, Constants::METHOD_CHANNEL_OPEN_OK);
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;
        $self->channelId = $buffer->consumeText();

        return $self;
    }

    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendText($this->channelId);

        return $buffer;
    }
}
