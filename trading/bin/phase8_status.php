#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Lists Phase 8 future upgrade stubs and their status.
 */

$root = dirname(__DIR__);
$roadmap = $root . '/PHASE8_ROADMAP.md';

echo "Phase 8 — Future Upgrade Ideas\n";
echo str_repeat('=', 40) . "\n";
echo "Roadmap: {$roadmap}\n\n";

$items = [
    'Real options chain data' => 'Trading\\Future\\OptionsChainClient',
    'Implied volatility' => 'Trading\\Future\\ImpliedVolatilityService',
    'Greeks (Delta, Theta)' => 'Trading\\Future\\GreeksCalculator',
    'Backtesting' => 'Trading\\Future\\Backtester',
];

foreach ($items as $label => $class) {
    echo sprintf("• %-28s  stub: %s  [planned]\n", $label, $class);
}

echo "\nNothing here is live yet — Phases 1–7 remain the working system.\n";
