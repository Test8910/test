#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Phase 4: simple options bias from price + RSI (no option chain).
 *
 * Usage:
 *   php bin/options_bias.php
 *   php bin/options_bias.php --symbol=QQQ
 *   php bin/options_bias.php --price=785 --strike=792 --rsi=72
 */

use Trading\Database;
use Trading\OptionsLogic;
use Trading\PriceRepository;
use Trading\RsiCalculator;

$config = require dirname(__DIR__) . '/src/bootstrap.php';
$options = getopt('', ['symbol::', 'price::', 'strike::', 'rsi::', 'help']);

if (isset($options['help'])) {
    echo "Usage:\n";
    echo "  php bin/options_bias.php [--symbol=QQQ]\n";
    echo "  php bin/options_bias.php --price=785 --strike=792 --rsi=72\n";
    exit(0);
}

if (isset($options['price'])) {
    $price = (float) $options['price'];
    $strike = isset($options['strike']) ? (float) $options['strike'] : null;
    $rsi = isset($options['rsi']) ? (float) $options['rsi'] : null;
    $result = OptionsLogic::analyze($price, $rsi, [], $strike);
    print_bias('CUSTOM', $price, $rsi, $result);
    exit(0);
}

$pdo = Database::connection($config);
$repo = new PriceRepository($pdo);
$symbols = $repo->activeSymbols();
$only = isset($options['symbol']) ? strtoupper((string) $options['symbol']) : null;

foreach ($symbols as $row) {
    if ($only !== null && $row['symbol'] !== $only) {
        continue;
    }
    $closes = $repo->closes($row['symbol']);
    if ($closes === []) {
        continue;
    }
    $price = (float) end($closes);
    $rsi = RsiCalculator::calculateRSI($closes);
    $result = OptionsLogic::analyze($price, $rsi, $closes);
    print_bias($row['symbol'], $price, $rsi, $result);
}

function print_bias(string $symbol, float $price, ?float $rsi, array $result): void
{
    echo "{$symbol}\n";
    echo sprintf("  Price:  %s\n", number_format($price, 2));
    echo sprintf("  RSI:    %s  (bias: %s)\n", $rsi === null ? 'n/a' : number_format($rsi, 2), $result['rsi_bias']);
    echo sprintf("  Strike: %s\n", number_format($result['strike'], 2));
    echo sprintf(
        "  Call Bias: %s (%s) — %s\n",
        $result['call']['strength'],
        $result['call']['note'],
        $result['call']['explained']
    );
    echo sprintf(
        "  Put Bias:  %s (%s) — %s\n",
        $result['put']['strength'],
        $result['put']['note'],
        $result['put']['explained']
    );
    echo "\n";
}
