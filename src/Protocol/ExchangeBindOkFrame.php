<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

class ExchangeBindOkFrame extends MethodFrame
{
    public function __construct()
    {
        parent::__construct(Constants::CLASS_EXCHANGE, Constants::METHOD_EXCHANGE_BIND_OK);
    }
}
