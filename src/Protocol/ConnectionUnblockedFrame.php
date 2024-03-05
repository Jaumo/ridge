<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

class ConnectionUnblockedFrame extends MethodFrame
{
    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_UNBLOCKED);

        $this->channel = Constants::CONNECTION_CHANNEL;
    }
}
