<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive\Drivers;

use Parallax\Rozmova\Archive\Contracts\Translator;

/**
 * Talks to a self-hosted translation service.
 *
 * Wire contract — POST /translate, JSON:
 *
 *   {"text": "...", "from": "uk", "to": "en"}
 *
 * Response:
 *
 *   {"text": "...", "engine": "facebook/nllb-200-distilled-600M"}
 *
 * An empty or missing translation raises rather than falling back to the
 * source. Echoing Ukrainian into an English field publishes a claim the
 * archive cannot stand behind.
 */
final class HttpTranslator implements Translator
{
    public function __construct(
        private readonly HttpTransport $transport,
        private readonly string $fallbackEngine = 'self-hosted-mt',
        private readonly string $path = '/translate',
    ) {
    }

    private string $lastEngine = '';

    public function engine(): string
    {
        return $this->lastEngine !== '' ? $this->lastEngine : $this->fallbackEngine;
    }

    public function translate(string $text, string $from, string $to): string
    {
        $response = $this->transport->postJson($this->path, [
            'text' => $text,
            'from' => $from,
            'to'   => $to,
        ]);

        $translated = $response['text'] ?? null;

        if (!is_string($translated) || trim($translated) === '') {
            throw TransportException::malformed($this->path, 'no usable "text" in response');
        }

        $this->lastEngine = is_string($response['engine'] ?? null) ? $response['engine'] : $this->fallbackEngine;

        return $translated;
    }
}
