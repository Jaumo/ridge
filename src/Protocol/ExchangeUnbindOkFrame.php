<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

class ExchangeUnbindOkFrame extends MethodFrame
{
    public function __construct()
    {
        parent::__construct(Constants::CLASS_EXCHANGE, Constants::METHOD_EXCHANGE_UNBIND_OK);
    }
}
