#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Phase 2: compute RSI(14) from stored closes.
 *
 * Usage:
 *   php bin/calculate_rsi.php
 *   php bin/calculate_rsi.php --symbol=QQQ
 *   php bin/calculate_rsi.php --period=14
 */

use Trading\Database;
use Trading\PriceRepository;
use Trading\RsiCalculator;

$config = require dirname(__DIR__) . '/src/bootstrap.php';

$options = getopt('', ['symbol::', 'period::', 'help']);
if (isset($options['help'])) {
    echo "Usage: php bin/calculate_rsi.php [--symbol=QQQ] [--period=14]\n";
    exit(0);
}

$period = isset($options['period']) ? (int) $options['period'] : RsiCalculator::DEFAULT_PERIOD;
$onlySymbol = isset($options['symbol']) ? strtoupper((string) $options['symbol']) : null;

$pdo = Database::connection($config);
$repo = new PriceRepository($pdo);
$symbols = $repo->activeSymbols();

if ($onlySymbol !== null) {
    $symbols = array_values(array_filter(
        $symbols,
        static fn (array $row): bool => $row['symbol'] === $onlySymbol
    ));
    if ($symbols === []) {
        fwrite(STDERR, "Unknown or inactive symbol: {$onlySymbol}\n");
        exit(1);
    }
}

echo "RSI({$period}) from close prices\n";
echo str_repeat('-', 48) . "\n";

foreach ($symbols as $symbolRow) {
    $symbol = $symbolRow['symbol'];
    $closes = $repo->closes($symbol);
    $rsi = RsiCalculator::calculateRSI($closes, $period);
    $signal = RsiCalculator::signal($rsi);

    if ($rsi === null) {
        echo sprintf("%-9s  bars=%-4d  RSI=n/a (need >= %d closes)\n", $symbol, count($closes), $period + 1);
        continue;
    }

    $trend = RsiCalculator::trend($closes);
    echo sprintf(
        "%-9s  bars=%-4d  last_close=%-10s  trend=%-4s  RSI=%6.2f  %s → %s\n",
        $symbol,
        count($closes),
        (string) end($closes),
        $trend,
        $rsi,
        RsiCalculator::signalLabel($signal),
        RsiCalculator::action($signal)
    );
}
