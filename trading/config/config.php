<?php

declare(strict_types=1);

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'trading_dashboard',
        'user' => 'trading',
        'pass' => 'trading',
        'charset' => 'utf8mb4',
    ],
    'history_range' => '1y',
    // EOD sync window — enough recent bars; full history loaded separately
    'sync_range' => '1mo',
    'request_delay_ms' => 400,
    // Cron target: 4:00 PM America/New_York, Mon–Fri (see cron/trading-dashboard)
    'eod_timezone' => 'America/New_York',
    'eod_hour' => 16,
];
