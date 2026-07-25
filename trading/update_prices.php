#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Phase 6 — synchronization entrypoint for cron.
 *
 * Cron example (every 5 minutes):
 *   every-5-min: php /path/to/trading/update_prices.php >> /path/to/trading/storage/sync.log 2>&1
 *   Crontab expression: star-slash-5 star star star star
 *
 * What it does:
 *   1) Fetch latest OHLC prices
 *   2) Store / upsert into MySQL
 *   3) Recalculate RSI snapshots
 *   4) Run Smart Signal Engine (RSI + move + trend)
 */

use Trading\Database;
use Trading\PriceRepository;
use Trading\PriceSync;
use Trading\RsiCalculator;
use Trading\YahooFinanceClient;

$config = require __DIR__ . '/src/bootstrap.php';

$options = getopt('', ['range::', 'period::', 'help']);
if (isset($options['help'])) {
    echo "Usage: php update_prices.php [--range=1mo] [--period=14]\n";
    exit(0);
}

$range = (string) ($options['range'] ?? ($config['sync_range'] ?? '1mo'));
$period = isset($options['period'])
    ? (int) $options['period']
    : RsiCalculator::DEFAULT_PERIOD;
$delayMs = (int) ($config['request_delay_ms'] ?? 400);

$pdo = Database::connection($config);
$repo = new PriceRepository($pdo);
$sync = new PriceSync($pdo, $repo, new YahooFinanceClient(), $delayMs);

$started = date('c');
echo "[{$started}] Starting price sync (range={$range}, rsi={$period})\n";

$result = $sync->run($range, $period);

foreach ($result['results'] as $row) {
    if ($row['ok']) {
        $rsi = $row['rsi'] === null ? 'n/a' : number_format((float) $row['rsi'], 2);
        echo sprintf(
            "  OK   %-10s bars=%-4d RSI=%s  smart=%s (%s)\n",
            $row['symbol'],
            (int) $row['bars'],
            $rsi,
            $row['smart_signal'] ?? 'n/a',
            $row['confidence'] ?? 'n/a'
        );
    } else {
        echo sprintf("  FAIL %-10s %s\n", $row['symbol'], $row['error'] ?? 'unknown error');
    }
}

echo sprintf(
    "[%s] Done status=%s ok=%d failed=%d run_id=%d\n",
    date('c'),
    $result['status'],
    $result['symbols_ok'],
    $result['symbols_failed'],
    $result['run_id']
);

exit($result['status'] === 'error' ? 1 : 0);
