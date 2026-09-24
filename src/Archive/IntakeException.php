<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive;

use RuntimeException;

final class IntakeException extends RuntimeException
{
    public static function missing(string $field): self
    {
        return new self(sprintf('Envelope is missing required field "%s".', $field));
    }

    public static function invalid(string $field, string $why): self
    {
        return new self(sprintf('Envelope field "%s" is invalid: %s', $field, $why));
    }

    public static function integrity(string $declared, string $actual): self
    {
        return new self(sprintf(
            'Payload does not match its declared sha256. Envelope said %s, bytes hash to %s.',
            substr($declared, 0, 16),
            substr($actual, 0, 16),
        ));
    }

    public static function empty(): self
    {
        return new self('Envelope carries neither payload bytes nor inline text.');
    }
}
