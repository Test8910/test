<?php

declare(strict_types=1);

namespace Trading\Future;

use RuntimeException;

/**
 * Phase 8 stub — option Greeks (Delta, Theta, …) — not implemented yet.
 *
 * @see ../../PHASE8_ROADMAP.md
 */
final class GreeksCalculator
{
    /**
     * @return array{
     *   delta: float,
     *   theta: float,
     *   gamma: float,
     *   vega: float
     * }
     */
    public function calculate(
        float $spot,
        float $strike,
        string $right,
        float $timeToExpiryYears,
        float $volatility,
        float $rate = 0.0
    ): array {
        throw new RuntimeException(
            'Phase 8: Greeks (Delta, Theta, …) are not implemented yet. See PHASE8_ROADMAP.md'
        );
    }
}
