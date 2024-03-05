<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

class TxRollbackOkFrame extends MethodFrame
{
    public function __construct()
    {
        parent::__construct(Constants::CLASS_TX, Constants::METHOD_TX_ROLLBACK_OK);
    }
}
