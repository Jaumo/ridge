<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Buffer;
use PHPinnacle\Ridge\Constants;

class QueuePurgeFrame extends MethodFrame
{
    /**
     * @var int
     */
    public $reserved1 = 0;

    /**
     * @var string
     */
    public $queue = '';

    /**
     * @var bool
     */
    public $nowait = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_PURGE);
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;
        $self->reserved1 = $buffer->consumeInt16();
        $self->queue = $buffer->consumeString();
        [$self->nowait] = $buffer->consumeBits(1);

        return $self;
    }

    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendInt16($this->reserved1);
        $buffer->appendString($this->queue);
        $buffer->appendBits([$this->nowait]);

        return $buffer;
    }
}
