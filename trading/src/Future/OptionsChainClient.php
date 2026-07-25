<?php

declare(strict_types=1);

namespace Trading\Future;

use RuntimeException;

/**
 * Phase 8 stub — real options chain ingestion (not implemented yet).
 *
 * @see ../../PHASE8_ROADMAP.md
 */
final class OptionsChainClient
{
    /**
     * @return list<array{
     *   symbol: string,
     *   expiry: string,
     *   strike: float,
     *   right: 'C'|'P',
     *   bid: ?float,
     *   ask: ?float,
     *   volume: ?int,
     *   open_interest: ?int
     * }>
     */
    public function fetchChain(string $underlying, ?string $expiry = null): array
    {
        throw new RuntimeException(
            'Phase 8: real options chain data is not implemented yet. See PHASE8_ROADMAP.md'
        );
    }
}
