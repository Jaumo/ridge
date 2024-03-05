<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

class ConfirmSelectOkFrame extends MethodFrame
{
    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONFIRM, Constants::METHOD_CONFIRM_SELECT_OK);
    }
}
