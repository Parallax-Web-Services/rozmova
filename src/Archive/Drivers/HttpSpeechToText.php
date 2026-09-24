<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive\Drivers;

use Parallax\Rozmova\Archive\Contracts\SpeechToText;

/**
 * Talks to a self-hosted ASR service.
 *
 * Wire contract — POST /transcribe, multipart:
 *
 *   audio      the media file
 *   language   BCP-47 tag, e.g. "uk"
 *
 * Response:
 *
 *   {"text": "...", "engine": "nvidia/stt_ua_fastconformer_hybrid_large_pc"}
 *
 * "engine" is not decoration. It lands in the archive record against every
 * transcript, so a record published in 2026 still says which model produced it
 * after the model has been replaced twice.
 */
final class HttpSpeechToText implements SpeechToText
{
    public function __construct(
        private readonly HttpTransport $transport,
        private readonly string $fallbackEngine = 'self-hosted-asr',
        private readonly string $path = '/transcribe',
    ) {
    }

    private string $lastEngine = '';

    public function engine(): string
    {
        return $this->lastEngine !== '' ? $this->lastEngine : $this->fallbackEngine;
    }

    public function transcribe(string $audio, string $language): string
    {
        $response = $this->transport->postMultipart(
            $this->path,
            ['language' => $language],
            ['audio' => ['bytes' => $audio, 'filename' => 'audio.wav', 'type' => 'application/octet-stream']],
        );

        $text = $response['text'] ?? null;

        if (!is_string($text) || trim($text) === '') {
            throw TransportException::malformed($this->path, 'no usable "text" in response');
        }

        $this->lastEngine = is_string($response['engine'] ?? null) ? $response['engine'] : $this->fallbackEngine;

        return $text;
    }
}
