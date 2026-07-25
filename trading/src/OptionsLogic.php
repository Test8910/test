<?php

declare(strict_types=1);

namespace Trading;

/**
 * Phase 4 — simple options bias (no real option pricing).
 *
 * RSI directional bias:
 *   RSI > 70 → market high → PUT bias
 *   RSI < 30 → market low  → CALL bias
 *
 * Strike example (Price 785 / Strike 792):
 *   Call = price goes ABOVE strike
 *   Put  = price goes BELOW strike
 *
 * Strength uses distance vs a rough “realistic move” from recent daily ranges.
 */
final class OptionsLogic
{
    /**
     * Suggest a clean nearby strike slightly above spot (OTM call / ITM put style),
     * similar to Price 785 → Strike 792.
     */
    public static function suggestStrike(float $price): float
    {
        if ($price <= 0) {
            return 0.0;
        }

        $increment = self::strikeIncrement($price);
        // Aim ~0.8–1.2% above spot, then snap to increment
        $target = $price * 1.01;
        $strike = ceil($target / $increment) * $increment;

        // Ensure strike is strictly above price for the demo framing
        if ($strike <= $price) {
            $strike += $increment;
        }

        return round($strike, $increment >= 1 ? 0 : 2);
    }

    /**
     * @param list<float|int|string> $closes oldest → newest
     * @return array{
     *   strike: float,
     *   expected_move_pct: float,
     *   rsi_bias: 'PUT'|'CALL'|'NONE',
     *   call: array{side: string, otm_pct: float, moneyness: string, strength: string, note: string},
     *   put: array{side: string, otm_pct: float, moneyness: string, strength: string, note: string},
     *   summary: string
     * }
     */
    public static function analyze(float $price, ?float $rsi, array $closes = [], ?float $strike = null): array
    {
        $strike ??= self::suggestStrike($price);
        $expectedMovePct = self::expectedMovePct($closes);
        $rsiBias = self::rsiBias($rsi);

        $call = self::sideBias('CALL', $price, $strike, $expectedMovePct, $rsiBias);
        $put = self::sideBias('PUT', $price, $strike, $expectedMovePct, $rsiBias);

        $summary = sprintf(
            'Strike %s · Call Bias: %s (%s) · Put Bias: %s (%s)',
            self::formatNumber($strike),
            $call['strength'],
            $call['note'],
            $put['strength'],
            $put['note']
        );

        return [
            'strike' => $strike,
            'expected_move_pct' => $expectedMovePct,
            'rsi_bias' => $rsiBias,
            'call' => $call,
            'put' => $put,
            'summary' => $summary,
        ];
    }

    public static function rsiBias(?float $rsi): string
    {
        if ($rsi === null) {
            return 'NONE';
        }
        if ($rsi > RsiCalculator::OVERBOUGHT) {
            return 'PUT';
        }
        if ($rsi < RsiCalculator::OVERSOLD) {
            return 'CALL';
        }

        return 'NONE';
    }

    /**
     * @return array{side: string, otm_pct: float, moneyness: string, strength: string, note: string}
     */
    private static function sideBias(
        string $side,
        float $price,
        float $strike,
        float $expectedMovePct,
        string $rsiBias
    ): array {
        if ($side === 'CALL') {
            // Call wins if price finishes ABOVE strike
            $otmPct = $strike > $price ? (($strike - $price) / $price) * 100 : 0.0;
            $moneyness = $strike > $price ? 'OTM' : ($strike < $price ? 'ITM' : 'ATM');
            $explained = 'betting price goes ABOVE ' . self::formatNumber($strike);
        } else {
            // Put wins if price finishes BELOW strike
            $otmPct = $price > $strike ? (($price - $strike) / $price) * 100 : 0.0;
            $moneyness = $price > $strike ? 'OTM' : ($price < $strike ? 'ITM' : 'ATM');
            $explained = 'betting price goes BELOW ' . self::formatNumber($strike);
        }

        $strength = self::distanceStrength($otmPct, $expectedMovePct, $moneyness);

        // RSI directional tilt
        if ($rsiBias === $side) {
            $strength = self::bumpStrength($strength, +1);
            $note = $moneyness === 'OTM' && $otmPct > max(1.5, $expectedMovePct)
                ? 'RSI favors ' . $side . ' but still stretched'
                : 'RSI favors ' . $side;
        } elseif ($rsiBias !== 'NONE' && $rsiBias !== $side) {
            $strength = self::bumpStrength($strength, -1);
            $note = $moneyness === 'OTM'
                ? 'far OTM / RSI against'
                : 'RSI against';
        } else {
            $note = match (true) {
                $moneyness === 'ITM' => 'closer to realistic move',
                $otmPct <= max(0.8, $expectedMovePct * 0.5) => 'closer to realistic move',
                $otmPct <= max(2.0, $expectedMovePct) => 'moderate distance',
                default => 'far OTM',
            };
        }

        return [
            'side' => $side,
            'otm_pct' => round($otmPct, 2),
            'moneyness' => $moneyness,
            'strength' => $strength,
            'note' => $note,
            'explained' => $explained,
        ];
    }

    private static function distanceStrength(float $otmPct, float $expectedMovePct, string $moneyness): string
    {
        if ($moneyness === 'ITM' || $moneyness === 'ATM') {
            return 'Strong';
        }

        $near = max(0.8, $expectedMovePct * 0.5);
        $mid = max(2.0, $expectedMovePct);

        if ($otmPct <= $near) {
            return 'Strong';
        }
        if ($otmPct <= $mid) {
            return 'Moderate';
        }

        return 'Weak';
    }

    private static function bumpStrength(string $strength, int $delta): string
    {
        $order = ['Weak', 'Moderate', 'Strong'];
        $idx = array_search($strength, $order, true);
        if ($idx === false) {
            return $strength;
        }

        $idx = max(0, min(count($order) - 1, $idx + $delta));
        return $order[$idx];
    }

    /**
     * Rough 5-session expected move from recent absolute daily % changes.
     *
     * @param list<float|int|string> $closes
     */
    private static function expectedMovePct(array $closes): float
    {
        $values = array_values(array_map('floatval', $closes));
        $n = count($values);
        if ($n < 6) {
            return 1.5; // fallback ~1.5%
        }

        $moves = [];
        $start = max(1, $n - 20);
        for ($i = $start; $i < $n; $i++) {
            if ($values[$i - 1] == 0.0) {
                continue;
            }
            $moves[] = abs(($values[$i] - $values[$i - 1]) / $values[$i - 1]) * 100;
        }

        if ($moves === []) {
            return 1.5;
        }

        $avgDaily = array_sum($moves) / count($moves);
        // ~5 trading-day horizon
        return round(max(0.5, $avgDaily * sqrt(5)), 2);
    }

    private static function strikeIncrement(float $price): float
    {
        return match (true) {
            $price >= 10000 => 50.0,
            $price >= 1000 => 10.0,
            $price >= 200 => 5.0,
            $price >= 50 => 1.0,
            $price >= 10 => 0.5,
            default => 0.1,
        };
    }

    private static function formatNumber(float $value): string
    {
        if (abs($value - round($value)) < 0.001) {
            return (string) (int) round($value);
        }

        return number_format($value, 2, '.', '');
    }
}
