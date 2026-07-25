<?php

declare(strict_types=1);

namespace Trading;

/**
 * Phase 7 — Smart Signal Engine.
 *
 * Combines:
 *   - RSI (primary PUT / CALL / HOLD thresholds)
 *   - Price movement (recent % change)
 *   - Market trend (short lookback direction)
 */
final class SmartSignalEngine
{
    public const SIGNAL_PUT = 'PUT';
    public const SIGNAL_CALL = 'CALL';
    public const SIGNAL_HOLD = 'HOLD';

    /**
     * @param list<float|int|string> $closes oldest → newest
     * @return array{
     *   signal: string,
     *   confidence: string,
     *   score: float,
     *   rsi: ?float,
     *   trend: string,
     *   change_pct: ?float,
     *   reasons: list<string>,
     *   label: string
     * }
     */
    public static function evaluate(
        array $closes,
        ?float $rsi = null,
        int $rsiPeriod = RsiCalculator::DEFAULT_PERIOD,
        int $trendLookback = 5
    ): array {
        $prices = array_values(array_map('floatval', $closes));
        $rsi ??= RsiCalculator::calculateRSI($prices, $rsiPeriod);
        $trend = self::marketTrend($prices, $trendLookback);
        $changePct = self::recentChangePct($prices);

        // Base rule (as specified)
        if ($rsi !== null && $rsi > RsiCalculator::OVERBOUGHT) {
            $signal = self::SIGNAL_PUT;
            $score = -40.0;
            $reasons = [sprintf('RSI %.2f > 70 (overbought)', $rsi)];
        } elseif ($rsi !== null && $rsi < RsiCalculator::OVERSOLD) {
            $signal = self::SIGNAL_CALL;
            $score = 40.0;
            $reasons = [sprintf('RSI %.2f < 30 (oversold)', $rsi)];
        } else {
            $signal = self::SIGNAL_HOLD;
            $score = 0.0;
            $reasons = [
                $rsi === null
                    ? 'RSI unavailable'
                    : sprintf('RSI %.2f in neutral zone', $rsi),
            ];
        }

        // Price movement adjustments
        if ($changePct !== null) {
            if ($changePct >= 1.0) {
                $score -= 15;
                $reasons[] = sprintf('Price up %.2f%% (extension risk)', $changePct);
            } elseif ($changePct <= -1.0) {
                $score += 15;
                $reasons[] = sprintf('Price down %.2f%% (rebound potential)', $changePct);
            } else {
                $reasons[] = sprintf('Price move %.2f%% (quiet)', $changePct);
            }
        }

        // Market trend adjustments
        if ($trend === 'up') {
            $score -= 10;
            $reasons[] = 'Short-term market trend: up';
        } elseif ($trend === 'down') {
            $score += 10;
            $reasons[] = 'Short-term market trend: down';
        } else {
            $reasons[] = 'Short-term market trend: flat';
        }

        // Re-resolve signal from combined score, but keep RSI hard gates
        if ($rsi !== null && $rsi > RsiCalculator::OVERBOUGHT) {
            $signal = self::SIGNAL_PUT;
        } elseif ($rsi !== null && $rsi < RsiCalculator::OVERSOLD) {
            $signal = self::SIGNAL_CALL;
        } elseif ($score <= -25) {
            $signal = self::SIGNAL_PUT;
            $reasons[] = 'Combined score favors PUT';
        } elseif ($score >= 25) {
            $signal = self::SIGNAL_CALL;
            $reasons[] = 'Combined score favors CALL';
        } else {
            $signal = self::SIGNAL_HOLD;
        }

        // Conflict dampening: RSI HOLD but strong contrary trend/move stays HOLD with low confidence
        $confidence = self::confidence($signal, $rsi, $changePct, $trend, $score);

        return [
            'signal' => $signal,
            'confidence' => $confidence,
            'score' => round($score, 2),
            'rsi' => $rsi,
            'trend' => $trend,
            'change_pct' => $changePct,
            'reasons' => $reasons,
            'label' => $signal . ' (' . $confidence . ')',
        ];
    }

    /**
     * @param list<float> $prices
     */
    public static function marketTrend(array $prices, int $lookback = 5): string
    {
        $n = count($prices);
        if ($n < 2) {
            return 'flat';
        }

        $span = min($lookback, $n - 1);
        $start = $prices[$n - 1 - $span];
        $end = $prices[$n - 1];
        if ($start == 0.0) {
            return 'flat';
        }

        $pct = (($end - $start) / $start) * 100;
        if ($pct >= 0.35) {
            return 'up';
        }
        if ($pct <= -0.35) {
            return 'down';
        }

        return 'flat';
    }

    /**
     * @param list<float> $prices
     */
    public static function recentChangePct(array $prices): ?float
    {
        $n = count($prices);
        if ($n < 2 || $prices[$n - 2] == 0.0) {
            return null;
        }

        return round((($prices[$n - 1] - $prices[$n - 2]) / $prices[$n - 2]) * 100, 2);
    }

    private static function confidence(
        string $signal,
        ?float $rsi,
        ?float $changePct,
        string $trend,
        float $score
    ): string {
        $abs = abs($score);

        if ($signal === self::SIGNAL_HOLD) {
            return $abs < 15 ? 'high' : 'medium';
        }

        $aligned = false;
        if ($signal === self::SIGNAL_PUT) {
            $aligned = ($rsi !== null && $rsi > 65) || ($changePct !== null && $changePct > 0.5) || $trend === 'up';
        }
        if ($signal === self::SIGNAL_CALL) {
            $aligned = ($rsi !== null && $rsi < 35) || ($changePct !== null && $changePct < -0.5) || $trend === 'down';
        }

        if ($aligned && $abs >= 45) {
            return 'high';
        }
        if ($abs >= 25) {
            return 'medium';
        }

        return 'low';
    }
}
