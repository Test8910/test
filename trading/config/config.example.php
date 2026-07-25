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
    // Pause between symbol requests to reduce rate-limit risk
    'request_delay_ms' => 400,
];
