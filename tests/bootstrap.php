<?php

declare(strict_types=1);

/**
 * Minimal PSR-4 autoloader so the suite runs without `composer install`.
 * Composer's autoloader is used in real projects; this keeps the tests
 * dependency-free.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Parallax\\Rozmova\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($path)) {
        require $path;
    }
});
