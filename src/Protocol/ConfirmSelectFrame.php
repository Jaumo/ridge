<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Buffer;
use PHPinnacle\Ridge\Constants;

class ConfirmSelectFrame extends MethodFrame
{
    /**
     * @var bool
     */
    public $nowait = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONFIRM, Constants::METHOD_CONFIRM_SELECT);
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;
        [$self->nowait] = $buffer->consumeBits(1);

        return $self;
    }

    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendBits([$this->nowait]);

        return $buffer;
    }
}
