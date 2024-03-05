<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

class BasicRecoverOkFrame extends MethodFrame
{
    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_RECOVER_OK);
    }
}
