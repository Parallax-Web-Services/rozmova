<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive\Sinks;

use Parallax\Rozmova\Archive\Contracts\Publisher;
use Parallax\Rozmova\Archive\Record;
use RuntimeException;

/**
 * Writes the publishable payload to disk. Stands in for Relay so the chain runs
 * before Relay exists.
 *
 * Unlike the archive store this one overwrites: publishing the corrected
 * version of an item is the point. Correction history lives in the archive,
 * where each revision has its own digest.
 */
final class FilesystemPublisher implements Publisher
{
    public function __construct(private readonly string $root)
    {
    }

    public function publish(Record $record): string
    {
        $safe = trim(preg_replace('#[^A-Za-z0-9/_.-]+#', '-', $record->id) ?? 'item', '/');
        $path = sprintf('%s/%s.json', $this->root, $safe);
        $dir  = dirname($path);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Could not create %s', $dir));
        }

        $payload = json_encode(
            $record->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        $tmp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (file_put_contents($tmp, $payload) === false || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException(sprintf('Could not write %s', $path));
        }

        return $path;
    }
}
