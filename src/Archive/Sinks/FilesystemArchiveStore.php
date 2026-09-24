<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive\Sinks;

use Parallax\Rozmova\Archive\Contracts\ArchiveStore;
use Parallax\Rozmova\Archive\Record;
use RuntimeException;

/**
 * Content-addressed store on local disk.
 *
 * Stands in for Holo so the chain runs today, and demonstrates the two
 * properties the real thing needs: source bytes are addressed by their own
 * hash, so identical payloads are stored once and a reference verifies itself;
 * and existing objects are never rewritten, so an archive entry cannot be
 * silently replaced.
 */
final class FilesystemArchiveStore implements ArchiveStore
{
    public function __construct(private readonly string $root)
    {
    }

    public function store(Record $record, ?string $sourceBytes = null): string
    {
        $bytes = $sourceBytes ?? $record->sourceBytes;

        if ($bytes !== null && $bytes !== '') {
            $this->writeOnce($this->objectPath(hash('sha256', $bytes)), $bytes);
        }

        $digest = $record->digest();

        $this->writeOnce(
            $this->recordPath($record->id, $digest),
            json_encode($record->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );

        return 'sha256:' . $digest;
    }

    public function has(string $reference): bool
    {
        $digest = str_starts_with($reference, 'sha256:') ? substr($reference, 7) : $reference;

        return $this->findRecord($digest) !== null;
    }

    public function read(string $reference): ?array
    {
        $digest = str_starts_with($reference, 'sha256:') ? substr($reference, 7) : $reference;
        $path   = $this->findRecord($digest);

        if ($path === null) {
            return null;
        }

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function findRecord(string $digest): ?string
    {
        $matches = glob($this->root . '/records/*/' . $digest . '.json', GLOB_NOSORT)
            ?: glob($this->root . '/records/**/' . $digest . '.json', GLOB_NOSORT);

        if ($matches) {
            return $matches[0];
        }

        // ids may nest arbitrarily deep, so fall back to a walk.
        $found = null;
        $dir   = $this->root . '/records';

        if (!is_dir($dir)) {
            return null;
        }

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($it as $file) {
            if ($file->getFilename() === $digest . '.json') {
                $found = $file->getPathname();
                break;
            }
        }

        return $found;
    }

    private function objectPath(string $hash): string
    {
        return sprintf('%s/objects/%s/%s/%s', $this->root, substr($hash, 0, 2), substr($hash, 2, 2), $hash);
    }

    private function recordPath(string $id, string $digest): string
    {
        $safe = trim(preg_replace('#[^A-Za-z0-9/_.-]+#', '-', $id) ?? 'item', '/');

        return sprintf('%s/records/%s/%s.json', $this->root, $safe, $digest);
    }

    private function writeOnce(string $path, string $contents): void
    {
        if (is_file($path)) {
            return; // content-addressed: same address means same bytes
        }

        $dir = dirname($path);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Could not create %s', $dir));
        }

        // Write to a temp name and rename, so a reader never sees a partial file.
        $tmp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (file_put_contents($tmp, $contents) === false || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException(sprintf('Could not write %s', $path));
        }
    }
}
