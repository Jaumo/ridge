<?php


declare(strict_types=1);

namespace PHPinnacle\Ridge\Exception;

/**
 *
 */
final class ConnectionException extends RidgeException
{
    public static function writeFailed(\Throwable $previous): self
    {
        return new self(
            \sprintf('Error writing to socket: %s', $previous->getMessage()),
            (int)$previous->getCode(),
            $previous
        );
    }

    public static function socketClosed(): self
    {
        return new self('Attempting to write to a closed socket');
    }

    public static function lostConnection(): self
    {
        return new self('Socket was closed unexpectedly');
    }
}
