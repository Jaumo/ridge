<?php


declare(strict_types=1);

namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

class HeartbeatFrame extends AbstractFrame
{
    public function __construct()
    {
        parent::__construct(Constants::FRAME_HEARTBEAT, Constants::CONNECTION_CHANNEL, 0, '');
    }
}
