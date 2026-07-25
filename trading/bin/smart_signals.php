#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Phase 7 — Smart Signal Engine CLI.
 *
 * Usage:
 *   php bin/smart_signals.php
 *   php bin/smart_signals.php --symbol=QQQ
 */

use Trading\Database;
use Trading\PriceRepository;
use Trading\RsiCalculator;
use Trading\SmartSignalEngine;

$config = require dirname(__DIR__) . '/src/bootstrap.php';
$options = getopt('', ['symbol::', 'help']);

if (isset($options['help'])) {
    echo "Usage: php bin/smart_signals.php [--symbol=QQQ]\n";
    exit(0);
}

$repo = new PriceRepository(Database::connection($config));
$only = isset($options['symbol']) ? strtoupper((string) $options['symbol']) : null;

echo "Smart Signal Engine (RSI + price movement + market trend)\n";
echo str_repeat('-', 64) . "\n";

foreach ($repo->activeSymbols() as $row) {
    if ($only !== null && $row['symbol'] !== $only) {
        continue;
    }

    $closes = $repo->closes($row['symbol']);
    $smart = SmartSignalEngine::evaluate($closes);

    echo sprintf("%s\n", $row['symbol']);
    echo sprintf(
        "  Signal: %-4s  Confidence: %-6s  Score: %s\n",
        $smart['signal'],
        $smart['confidence'],
        number_format($smart['score'], 2)
    );
    echo sprintf(
        "  RSI: %s  Trend: %s  Move: %s%%\n",
        $smart['rsi'] === null ? 'n/a' : number_format($smart['rsi'], 2),
        $smart['trend'],
        $smart['change_pct'] === null ? 'n/a' : number_format($smart['change_pct'], 2)
    );
    foreach ($smart['reasons'] as $reason) {
        echo "  - {$reason}\n";
    }
    echo "\n";
}
