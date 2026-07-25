<?php

declare(strict_types=1);

namespace Trading;

/**
 * 14-period RSI from close prices only.
 *
 * Seed averages match the classic SMA approach from Phase 2:
 *   avgGain / avgLoss over the first $period changes, then RS → RSI.
 * Later values use Wilder smoothing (standard RSI).
 */
final class RsiCalculator
{
    public const DEFAULT_PERIOD = 14;
    public const OVERBOUGHT = 70.0;
    public const OVERSOLD = 30.0;

    /**
     * Latest RSI for an ordered list of closes (oldest → newest).
     *
     * @param list<float|int|string> $prices
     */
    public static function calculateRSI(array $prices, int $period = self::DEFAULT_PERIOD): ?float
    {
        $series = self::series($prices, $period);
        if ($series === []) {
            return null;
        }

        return $series[array_key_last($series)];
    }

    /**
     * Full RSI series. Index N is the RSI at close index N.
     * Values before the first computable bar are omitted.
     *
     * @param list<float|int|string> $prices
     * @return array<int, float> keyed by close index
     */
    public static function series(array $prices, int $period = self::DEFAULT_PERIOD): array
    {
        if ($period < 1) {
            throw new \InvalidArgumentException('RSI period must be >= 1');
        }

        $closes = [];
        foreach ($prices as $price) {
            if ($price === null || $price === '') {
                continue;
            }
            $closes[] = (float) $price;
        }

        // Need period changes ⇒ period + 1 closes for the first RSI value
        if (count($closes) < $period + 1) {
            return [];
        }

        $gains = [];
        $losses = [];

        for ($i = 1; $i < count($closes); $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            if ($change > 0) {
                $gains[] = $change;
                $losses[] = 0.0;
            } else {
                $gains[] = 0.0;
                $losses[] = abs($change);
            }
        }

        $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;

        $series = [];
        // First RSI aligns with close index = $period
        $series[$period] = self::rsiFromAverages($avgGain, $avgLoss);

        for ($i = $period; $i < count($gains); $i++) {
            $avgGain = (($avgGain * ($period - 1)) + $gains[$i]) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $losses[$i]) / $period;
            $series[$i + 1] = self::rsiFromAverages($avgGain, $avgLoss);
        }

        return $series;
    }

    public static function signal(?float $rsi): string
    {
        if ($rsi === null) {
            return 'n/a';
        }
        if ($rsi >= self::OVERBOUGHT) {
            return 'overbought';
        }
        if ($rsi <= self::OVERSOLD) {
            return 'oversold';
        }

        return 'neutral';
    }

    /** Human label with status marker, e.g. "🔴 Overbought". */
    public static function signalLabel(string $signal): string
    {
        return match ($signal) {
            'overbought' => '🔴 Overbought',
            'oversold' => '🟢 Oversold',
            'neutral' => '⚪ Neutral',
            default => 'n/a',
        };
    }

    /** Options-style action from RSI signal. */
    public static function action(string $signal): string
    {
        return match ($signal) {
            'overbought' => 'Consider PUT',
            'oversold' => 'Consider CALL',
            'neutral' => 'Wait / Hold',
            default => 'n/a',
        };
    }

    /**
     * Short-term trend from recent closes (last vs prior bar).
     *
     * @param list<float|int|string> $prices
     * @return 'up'|'down'|'flat'|'n/a'
     */
    public static function trend(array $prices): string
    {
        $closes = array_values(array_map('floatval', $prices));
        $n = count($closes);
        if ($n < 2) {
            return 'n/a';
        }

        $delta = $closes[$n - 1] - $closes[$n - 2];
        if (abs($delta) < 0.0001) {
            return 'flat';
        }

        return $delta > 0 ? 'up' : 'down';
    }

    public static function trendLabel(string $trend): string
    {
        return match ($trend) {
            'up' => '▲ Up',
            'down' => '▼ Down',
            'flat' => '▶ Flat',
            default => 'n/a',
        };
    }

    private static function rsiFromAverages(float $avgGain, float $avgLoss): float
    {
        if ($avgLoss == 0.0) {
            return 100.0;
        }

        $rs = $avgGain / $avgLoss;
        return round(100 - (100 / (1 + $rs)), 2);
    }
}
