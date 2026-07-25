<?php

declare(strict_types=1);

namespace Trading\Future;

use RuntimeException;

/**
 * Phase 8 stub — signal backtesting (not implemented yet).
 *
 * Planned first slice: replay SmartSignalEngine on historical closes
 * and measure forward returns after PUT/CALL/HOLD.
 *
 * @see ../../PHASE8_ROADMAP.md
 */
final class Backtester
{
    /**
     * @return array{
     *   symbol: string,
     *   trades: int,
     *   hit_rate: float,
     *   avg_forward_return: float,
     *   max_drawdown: float
     * }
     */
    public function run(string $symbol, string $from, string $to, int $forwardDays = 5): array
    {
        throw new RuntimeException(
            'Phase 8: backtesting is not implemented yet. See PHASE8_ROADMAP.md'
        );
    }
}
