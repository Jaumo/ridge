<?php


namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

class ContentBodyFrame extends AbstractFrame
{
    public function __construct(?int $channel = null, ?int $payloadSize = null, ?string $payload = null)
    {
        parent::__construct(Constants::FRAME_BODY, $channel, $payloadSize, $payload);
    }
}
