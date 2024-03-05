<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

class BasicQosOkFrame extends MethodFrame
{
    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_QOS_OK);
    }
}
