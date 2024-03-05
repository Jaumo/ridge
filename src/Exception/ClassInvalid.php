<?php


declare(strict_types=1);

namespace PHPinnacle\Ridge\Exception;

final class ClassInvalid extends ProtocolException
{
    public function __construct(int $classId)
    {
        parent::__construct(\sprintf('Unhandled method frame class `%d`.', $classId));
    }
}
