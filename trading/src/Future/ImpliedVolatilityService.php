<?php

declare(strict_types=1);

namespace Trading\Future;

use RuntimeException;

/**
 * Phase 8 stub — implied volatility (not implemented yet).
 *
 * @see ../../PHASE8_ROADMAP.md
 */
final class ImpliedVolatilityService
{
    /**
     * @return array{iv: float, realized_vol: ?float, iv_rank: ?float}
     */
    public function forContract(
        float $spot,
        float $strike,
        string $right,
        float $marketPrice,
        float $timeToExpiryYears,
        float $rate = 0.0
    ): array {
        throw new RuntimeException(
            'Phase 8: implied volatility is not implemented yet. See PHASE8_ROADMAP.md'
        );
    }
}
