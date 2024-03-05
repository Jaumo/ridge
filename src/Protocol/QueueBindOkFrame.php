<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

class QueueBindOkFrame extends MethodFrame
{
    public function __construct()
    {
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_BIND_OK);
    }
}
