<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

class ExchangeDeleteOkFrame extends MethodFrame
{
    public function __construct()
    {
        parent::__construct(Constants::CLASS_EXCHANGE, Constants::METHOD_EXCHANGE_DELETE_OK);
    }
}
