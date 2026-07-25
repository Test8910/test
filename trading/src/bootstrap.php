<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Trading\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

$configPath = dirname(__DIR__) . '/config/config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Missing config/config.php. Copy config.example.php first.\n");
    exit(1);
}

/** @var array $config */
$config = require $configPath;

return $config;
