#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Phase 1 data collection:
 * Fetch daily OHLC from Yahoo Finance and upsert into MySQL `prices`.
 *
 * Usage:
 *   php bin/ingest_prices.php
 *   php bin/ingest_prices.php --symbol=QQQ
 *   php bin/ingest_prices.php --range=6mo
 */

use Trading\Database;
use Trading\PriceRepository;
use Trading\YahooFinanceClient;

$config = require dirname(__DIR__) . '/src/bootstrap.php';

$options = getopt('', ['symbol::', 'range::', 'help']);
if (isset($options['help'])) {
    echo "Usage: php bin/ingest_prices.php [--symbol=QQQ] [--range=1y]\n";
    exit(0);
}

$range = $options['range'] ?? ($config['history_range'] ?? '1y');
$onlySymbol = isset($options['symbol']) ? strtoupper((string) $options['symbol']) : null;
$delayMs = (int) ($config['request_delay_ms'] ?? 400);

$pdo = Database::connection($config);
$repo = new PriceRepository($pdo);
$client = new YahooFinanceClient();

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

if ($symbols === []) {
    fwrite(STDERR, "No active symbols found. Run bin/setup_db.php first.\n");
    exit(1);
}

echo "Ingesting daily OHLC (range={$range})...\n";

$failures = 0;
foreach ($symbols as $index => $symbolRow) {
    $symbol = $symbolRow['symbol'];
    $yahoo = $symbolRow['yahoo_symbol'];

    try {
        echo sprintf('[%d/%d] %s (%s) ... ', $index + 1, count($symbols), $symbol, $yahoo);
        $rows = $client->fetchDailyOhlc($yahoo, (string) $range);
        $upserted = $repo->upsertPrices($symbol, $rows);
        echo "ok ({$upserted} bars)\n";
    } catch (Throwable $e) {
        $failures++;
        echo "FAILED\n";
        fwrite(STDERR, '  ' . $e->getMessage() . "\n");
    }

    if ($index < count($symbols) - 1 && $delayMs > 0) {
        usleep($delayMs * 1000);
    }
}

echo "\nPrice summary:\n";
foreach ($repo->summary() as $row) {
    echo sprintf(
        "  %-9s  bars=%-4d  %s → %s  last_close=%s\n",
        $row['symbol'],
        (int) $row['bars'],
        $row['first_date'],
        $row['last_date'],
        $row['last_close']
    );
}

exit($failures > 0 ? 1 : 0);
