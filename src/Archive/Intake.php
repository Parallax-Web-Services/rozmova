<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * The boundary between whatever fetches things and this pipeline.
 *
 * Ingest belongs to Relay, not here. What this class fixes is the handover: an
 * envelope describing where an item came from, plus either the bytes or the
 * text. Transport is deliberately unspecified -- webhook, queue, file drop, all
 * produce the same envelope.
 *
 * Envelope:
 *
 *   id            stable archive identifier, e.g. "tryzantha/2026-09-24/address"
 *   source_url    where it was fetched from
 *   source_label  who published it
 *   captured_at   RFC3339, when it was fetched
 *   media_type    of the payload
 *   sha256        optional; VERIFIED against the bytes when present
 *   text          optional; for items published as text rather than speech
 *
 * The hash is checked rather than trusted. An archive that records a digest it
 * never verified is recording a claim, not a fact -- and the one moment it can
 * still be checked cheaply is here, before anything derived exists.
 */
final class Intake
{
    private const REQUIRED = ['id', 'source_url', 'source_label', 'captured_at', 'media_type'];

    /**
     * @param array<string, mixed> $envelope
     * @param string|null $bytes Payload as fetched. Null for a text-only item.
     */
    public function accept(array $envelope, ?string $bytes = null): Record
    {
        foreach (self::REQUIRED as $field) {
            if (!isset($envelope[$field]) || !is_string($envelope[$field]) || trim($envelope[$field]) === '') {
                throw IntakeException::missing($field);
            }
        }

        $text = isset($envelope['text']) && is_string($envelope['text']) ? trim($envelope['text']) : '';

        if (($bytes === null || $bytes === '') && $text === '') {
            throw IntakeException::empty();
        }

        $capturedAt = $this->parseTimestamp((string) $envelope['captured_at']);

        // Hash whatever we were actually given, then check any claim about it.
        $material = $bytes ?? $text;
        $actual   = hash('sha256', $material);

        if (isset($envelope['sha256'])) {
            if (!is_string($envelope['sha256']) || !preg_match('/^[a-f0-9]{64}$/i', $envelope['sha256'])) {
                throw IntakeException::invalid('sha256', 'expected 64 hex characters');
            }

            if (!hash_equals(strtolower($envelope['sha256']), $actual)) {
                throw IntakeException::integrity(strtolower($envelope['sha256']), $actual);
            }
        }

        $record = Record::open(
            (string) $envelope['id'],
            new Provenance(
                (string) $envelope['source_url'],
                (string) $envelope['source_label'],
                $capturedAt,
                $actual,
                (string) $envelope['media_type'],
            ),
            $bytes,
        );

        // An item published as text arrives already transcribed. It is the
        // publisher's own words, so it is not marked machine-made.
        if ($text !== '') {
            $record = $record->with(Rendition::humanMade(
                Rendition::TRANSCRIPT,
                is_string($envelope['language'] ?? null) ? $envelope['language'] : 'uk',
                $text,
                (string) $envelope['source_label'],
            ));
        }

        return $record;
    }

    /**
     * Strictly RFC3339.
     *
     * Not `new DateTimeImmutable($value)`, which cheerfully accepts "last
     * tuesday" and "now" as relative expressions and would record a capture
     * time the item never had.
     */
    private function parseTimestamp(string $value): DateTimeImmutable
    {
        $normalised = preg_replace('/[Zz]$/', '+00:00', trim($value)) ?? $value;

        foreach ([DateTimeInterface::RFC3339, DateTimeInterface::RFC3339_EXTENDED] as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $normalised);
            $errors = DateTimeImmutable::getLastErrors();

            if ($parsed instanceof DateTimeImmutable
                && ($errors === false || (($errors['warning_count'] ?? 0) + ($errors['error_count'] ?? 0)) === 0)
            ) {
                if ($parsed > new DateTimeImmutable('+1 day')) {
                    throw IntakeException::invalid('captured_at', 'is in the future');
                }

                return $parsed;
            }
        }

        throw IntakeException::invalid('captured_at', 'expected an RFC3339 timestamp');
    }
}
