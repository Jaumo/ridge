<?php


declare(strict_types=1);

namespace PHPinnacle\Ridge\Exception;

/**
 *
 */
final class ConfigurationException extends RidgeException
{
    public static function emptyDSN(): self
    {
        return new self('Connection DSN can\'t be empty');
    }

    public static function incorrectDSN(string $dsn): self
    {
        return new self(\sprintf('Can\'t parse specified connection DSN (%s)', $dsn));
    }
}
