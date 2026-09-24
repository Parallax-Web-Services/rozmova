<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive\Sinks;

use Parallax\Rozmova\Archive\Contracts\Publisher;
use Parallax\Rozmova\Archive\Drivers\HttpTransport;
use Parallax\Rozmova\Archive\Drivers\TransportException;
use Parallax\Rozmova\Archive\Record;

/**
 * Hands a finished record to Relay.
 *
 * Wire contract — POST /publish, JSON: the record as produced by
 * Record::toArray(), digest included.
 *
 * Response:
 *
 *   {"reference": "https://..."}
 *
 * The digest travels with the payload so the receiving end can verify it did
 * not change in transit, and can recognise a republication of something it
 * already holds.
 */
final class HttpPublisher implements Publisher
{
    public function __construct(
        private readonly HttpTransport $transport,
        private readonly string $path = '/publish',
    ) {
    }

    public function publish(Record $record): string
    {
        $response = $this->transport->postJson($this->path, $record->toArray());

        $reference = $response['reference'] ?? null;

        if (!is_string($reference) || trim($reference) === '') {
            throw TransportException::malformed($this->path, 'no usable "reference" in response');
        }

        return $reference;
    }
}
