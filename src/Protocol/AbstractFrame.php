<?php


declare(strict_types=1);

namespace PHPinnacle\Ridge\Protocol;

abstract class AbstractFrame
{
    /**
     * @var int|null
     */
    public $type;

    /**
     * @var int|null
     */
    public $channel;

    /**
     * @var int|null
     */
    public $size;

    /**
     * @var string|null
     */
    public $payload;

    public function __construct(?int $type = null, ?int $channel = null, ?int $size = null, ?string $payload = null)
    {
        $this->type = $type;
        $this->channel = $channel;
        $this->size = $size;
        $this->payload = $payload;
    }
}