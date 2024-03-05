<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

class QueueUnbindOkFrame extends MethodFrame
{
    public function __construct()
    {
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_UNBIND_OK);
    }
}
