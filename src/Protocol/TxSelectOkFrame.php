<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

class TxSelectOkFrame extends MethodFrame
{
    public function __construct()
    {
        parent::__construct(Constants::CLASS_TX, Constants::METHOD_TX_SELECT_OK);
    }
}
