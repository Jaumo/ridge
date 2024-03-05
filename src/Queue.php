<?php


declare(strict_types=1);

namespace PHPinnacle\Ridge;

final class Queue
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $messages;

    /**
     * @var int
     */
    private $consumers;

    public function __construct(string $name, int $messages, int $consumers)
    {
        $this->name = $name;
        $this->messages = $messages;
        $this->consumers = $consumers;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function messages(): int
    {
        return $this->messages;
    }

    public function consumers(): int
    {
        return $this->consumers;
    }
}
