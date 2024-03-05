<?php


declare(strict_types=1);

namespace PHPinnacle\Ridge\Exception;

final class MethodInvalid extends ProtocolException
{
    public function __construct(int $classId, int $methodId)
    {
        parent::__construct(\sprintf('Unhandled method frame method `%d` in class `%d`.', $methodId, $classId));
    }
}
