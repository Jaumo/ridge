<?php

namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

class AccessRequestFrame extends MethodFrame
{
    /**
     * @var string
     */
    public $realm = '/data';

    /**
     * @var boolean
     */
    public $exclusive = false;

    /**
     * @var boolean
     */
    public $passive = true;

    /**
     * @var boolean
     */
    public $active = true;

    /**
     * @var boolean
     */
    public $write = true;

    /**
     * @var boolean
     */
    public $read = true;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_ACCESS, Constants::METHOD_ACCESS_REQUEST);
    }
}
