<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

class ChannelCloseOkFrame extends MethodFrame
{
    public function __construct()
    {
        parent::__construct(Constants::CLASS_CHANNEL, Constants::METHOD_CHANNEL_CLOSE_OK);
    }
}
