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
    'sync_range' => '1mo',
    'request_delay_ms' => 400,
];
