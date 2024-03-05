<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Buffer;
use PHPinnacle\Ridge\Constants;

class ConnectionBlockedFrame extends MethodFrame
{
    /**
     * @var string
     */
    public $reason = '';

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_BLOCKED);

        $this->channel = Constants::CONNECTION_CHANNEL;
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;
        $self->reason = $buffer->consumeString();

        return $self;
    }

    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendString($this->reason);

        return $buffer;
    }
}
