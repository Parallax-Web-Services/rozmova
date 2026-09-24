<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive\Drivers;

use RuntimeException;

final class TransportException extends RuntimeException
{
    public static function connection(string $url, string $detail): self
    {
        return new self(sprintf('Could not reach %s: %s', $url, $detail));
    }

    public static function status(string $url, int $status, string $body): self
    {
        return new self(sprintf(
            '%s returned HTTP %d: %s',
            $url,
            $status,
            mb_strimwidth(trim($body), 0, 200, '...'),
        ));
    }

    public static function malformed(string $url, string $detail): self
    {
        return new self(sprintf('%s returned unusable output: %s', $url, $detail));
    }
}
