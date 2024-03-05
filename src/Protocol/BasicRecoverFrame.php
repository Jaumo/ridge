<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Buffer;
use PHPinnacle\Ridge\Constants;

class BasicRecoverFrame extends MethodFrame
{
    /**
     * @var bool
     */
    public $requeue = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_RECOVER);
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;
        [$self->requeue] = $buffer->consumeBits(1);

        return $self;
    }

    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendBits([$this->requeue]);

        return $buffer;
    }
}
