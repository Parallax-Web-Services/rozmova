<?php

declare(strict_types=1);

/**
 * Stand-in inference service for the HTTP driver tests. Implements the wire
 * contract plus the failure modes the drivers are supposed to survive.
 */

$path  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$state = sys_get_temp_dir() . '/rozmova-fake-service.state';

header('Content-Type: application/json');

$reply = static function (array $body, int $status = 200): void {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
};

switch ($path) {
    case '/transcribe':
        $reply([
            'text'   => 'Зеленський подякував ЗСУ за оборону Бахмута.',
            'engine' => 'nvidia/stt_ua_fastconformer_hybrid_large_pc',
        ]);
        break;

    case '/translate':
        $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
        $reply([
            'text'   => 'Zelenskyy thanked the Armed Forces of Ukraine for the defence of Bakhmut.',
            'engine' => 'facebook/nllb-200-distilled-600M',
            'echo'   => $payload,
        ]);
        break;

    // Fails twice with 5xx, then succeeds. Exercises retry.
    case '/flaky':
        $n = (int) @file_get_contents($state);
        file_put_contents($state, (string) ($n + 1));

        if ($n < 2) {
            $reply(['error' => 'model warming up'], 503);
            break;
        }

        $reply(['text' => 'recovered', 'engine' => 'flaky']);
        break;

    case '/flaky/reset':
        @unlink($state);
        $reply(['ok' => true]);
        break;

    // Client error: the driver must not retry this.
    case '/bad':
        $n = (int) @file_get_contents($state . '.bad');
        file_put_contents($state . '.bad', (string) ($n + 1));
        $reply(['error' => 'unsupported language'], 400);
        break;

    case '/bad/count':
        $reply(['count' => (int) @file_get_contents($state . '.bad')]);
        break;

    case '/bad/reset':
        @unlink($state . '.bad');
        $reply(['ok' => true]);
        break;

    // Succeeds at the HTTP level but returns nothing usable.
    case '/empty':
        $reply(['text' => '   ', 'engine' => 'empty']);
        break;

    case '/garbage':
        header('Content-Type: text/plain');
        echo 'not json at all';
        break;

    default:
        $reply(['error' => 'not found'], 404);
}
