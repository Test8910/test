#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Apply database/schema.sql using the configured MySQL credentials.
 * Creates the database if the user has permission; otherwise run schema.sql as root.
 */

$config = require dirname(__DIR__) . '/src/bootstrap.php';
$db = $config['db'];
$schemaFile = dirname(__DIR__) . '/database/schema.sql';

if (!is_file($schemaFile)) {
    fwrite(STDERR, "Schema file not found: {$schemaFile}\n");
    exit(1);
}

$sql = file_get_contents($schemaFile);
if ($sql === false) {
    fwrite(STDERR, "Unable to read schema file\n");
    exit(1);
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;charset=%s',
    $db['host'],
    (int) $db['port'],
    $db['charset']
);

try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec($sql);
    echo "Schema applied successfully to database '{$db['name']}'.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Setup failed: ' . $e->getMessage() . "\n");
    fwrite(STDERR, "Tip: run as MySQL root if the app user cannot create databases:\n");
    fwrite(STDERR, "  mysql -u root < trading/database/schema.sql\n");
    exit(1);
}
