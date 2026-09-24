<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive\Drivers;

use CURLFile;

/**
 * Small JSON-over-HTTP client for self-hosted inference services.
 *
 * Retries connection failures and 5xx, never 4xx — a malformed request will
 * fail the same way three times, and the pipeline should hear about it once.
 * Every failure raises rather than returning a default, because the caller
 * publishes what it is handed and a silent empty string becomes a published
 * empty transcript.
 */
final class HttpTransport
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly float $timeout = 60.0,
        private readonly int $retries = 2,
        private readonly array $headers = [],
    ) {
    }

    public function postJson(string $path, array $payload): array
    {
        return $this->send($path, [
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => [...$this->headerLines(), 'Content-Type: application/json'],
        ]);
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, array{bytes: string, filename: string, type: string}> $files
     */
    public function postMultipart(string $path, array $fields, array $files): array
    {
        $temporary = [];
        $body      = $fields;

        foreach ($files as $name => $file) {
            $tmp = tempnam(sys_get_temp_dir(), 'rozmova');

            if ($tmp === false) {
                throw TransportException::connection($this->url($path), 'could not buffer upload');
            }

            file_put_contents($tmp, $file['bytes']);
            $temporary[] = $tmp;
            $body[$name] = new CURLFile($tmp, $file['type'], $file['filename']);
        }

        try {
            return $this->send($path, [
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $this->headerLines(),
            ]);
        } finally {
            foreach ($temporary as $tmp) {
                @unlink($tmp);
            }
        }
    }

    private function send(string $path, array $options): array
    {
        $url      = $this->url($path);
        $attempts = $this->retries + 1;
        $last     = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $handle = curl_init($url);

            curl_setopt_array($handle, $options + [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => (int) ceil($this->timeout),
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FAILONERROR    => false,
            ]);

            $body   = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $error  = curl_error($handle);

            curl_close($handle);

            if ($body === false || $error !== '') {
                $last = TransportException::connection($url, $error !== '' ? $error : 'no response');
            } elseif ($status >= 500) {
                $last = TransportException::status($url, $status, (string) $body);
            } elseif ($status >= 400) {
                // Client error: retrying will not help.
                throw TransportException::status($url, $status, (string) $body);
            } else {
                return $this->decode($url, (string) $body);
            }

            if ($attempt < $attempts) {
                usleep((int) (250_000 * (2 ** ($attempt - 1))));
            }
        }

        throw $last ?? TransportException::connection($url, 'exhausted retries');
    }

    private function decode(string $url, string $body): array
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw TransportException::malformed($url, $e->getMessage());
        }

        if (!is_array($decoded)) {
            throw TransportException::malformed($url, 'expected a JSON object');
        }

        return $decoded;
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /** @return list<string> */
    private function headerLines(): array
    {
        return array_map(
            static fn (string $k, string $v): string => "$k: $v",
            array_keys($this->headers),
            array_values($this->headers),
        );
    }
}
