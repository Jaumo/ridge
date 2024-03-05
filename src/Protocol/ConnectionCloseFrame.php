<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Buffer;
use PHPinnacle\Ridge\Constants;

class ConnectionCloseFrame extends MethodFrame
{
    /**
     * @var int
     */
    public $replyCode;

    /**
     * @var string
     */
    public $replyText = '';

    /**
     * @var int
     */
    public $closeClassId;

    /**
     * @var int
     */
    public $closeMethodId;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_CLOSE);

        $this->channel = Constants::CONNECTION_CHANNEL;
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;
        $self->replyCode = $buffer->consumeInt16();
        $self->replyText = $buffer->consumeString();
        $self->closeClassId = $buffer->consumeInt16();
        $self->closeMethodId = $buffer->consumeInt16();

        return $self;
    }

    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendInt16($this->replyCode);
        $buffer->appendString($this->replyText);
        $buffer->appendInt16($this->closeClassId);
        $buffer->appendInt16($this->closeMethodId);

        return $buffer;
    }
}
