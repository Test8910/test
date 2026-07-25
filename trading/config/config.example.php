<?php

declare(strict_types=1);

/**
 * Copy to config.php and adjust for your environment.
 */
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'trading_dashboard',
        'user' => 'trading',
        'pass' => 'trading',
        'charset' => 'utf8mb4',
    ],
    // Lookback window when ingesting from Yahoo Finance
    'history_range' => '1y',
    // Shorter window for cron sync (enough to catch latest bars)
    'sync_range' => '1mo',
    // Pause between symbol requests to reduce rate-limit risk
    'request_delay_ms' => 400,
    // EOD cron: 4:00 PM America/New_York, Mon–Fri (holidays skipped in update_prices.php)
    'eod_timezone' => 'America/New_York',
    'eod_hour' => 16,
];
