<?php

declare(strict_types=1);

namespace Trading;

use PDO;

final class PriceRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array{symbol: string, name: string, yahoo_symbol: string, market: string}>
     */
    public function activeSymbols(): array
    {
        $stmt = $this->pdo->query(
            'SELECT symbol, name, yahoo_symbol, market
             FROM symbols
             WHERE is_active = 1
             ORDER BY symbol'
        );

        return $stmt->fetchAll();
    }

    /**
     * Upsert OHLC rows for a symbol.
     *
     * @param list<array{
     *   date: string,
     *   open: ?float,
     *   high: ?float,
     *   low: ?float,
     *   close: float,
     *   volume: ?int
     * }> $rows
     */
    public function upsertPrices(string $symbol, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $sql = 'INSERT INTO prices (symbol, date, open, high, low, close, volume)
                VALUES (:symbol, :date, :open, :high, :low, :close, :volume)
                ON DUPLICATE KEY UPDATE
                  open = VALUES(open),
                  high = VALUES(high),
                  low = VALUES(low),
                  close = VALUES(close),
                  volume = VALUES(volume)';

        $stmt = $this->pdo->prepare($sql);
        $count = 0;

        $this->pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $stmt->execute([
                    ':symbol' => $symbol,
                    ':date' => $row['date'],
                    ':open' => $row['open'],
                    ':high' => $row['high'],
                    ':low' => $row['low'],
                    ':close' => $row['close'],
                    ':volume' => $row['volume'],
                ]);
                $count++;
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $count;
    }

    /**
     * Ordered closes for RSI (oldest → newest).
     *
     * @return list<float>
     */
    public function closes(string $symbol): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT close
             FROM prices
             WHERE symbol = :symbol
             ORDER BY date ASC'
        );
        $stmt->execute([':symbol' => $symbol]);

        return array_map(
            static fn (array $row): float => (float) $row['close'],
            $stmt->fetchAll()
        );
    }

    /**
     * @return list<array{symbol: string, bars: int, first_date: string, last_date: string, last_close: string}>
     */
    public function summary(): array
    {
        $sql = 'SELECT
                  symbol,
                  COUNT(*) AS bars,
                  MIN(date) AS first_date,
                  MAX(date) AS last_date,
                  (
                    SELECT p2.close
                    FROM prices p2
                    WHERE p2.symbol = p.symbol
                    ORDER BY p2.date DESC
                    LIMIT 1
                  ) AS last_close
                FROM prices p
                GROUP BY symbol
                ORDER BY symbol';

        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * Summary rows enriched with latest RSI(14) and signal.
     *
     * @return list<array{
     *   symbol: string,
     *   bars: int,
     *   first_date: string,
     *   last_date: string,
     *   last_close: string,
     *   rsi: ?float,
     *   signal: string
     * }>
     */
    public function summaryWithRsi(int $period = RsiCalculator::DEFAULT_PERIOD): array
    {
        $rows = [];
        foreach ($this->summary() as $row) {
            $rsi = RsiCalculator::calculateRSI($this->closes($row['symbol']), $period);
            $rows[] = [
                ...$row,
                'rsi' => $rsi,
                'signal' => RsiCalculator::signal($rsi),
            ];
        }

        return $rows;
    }
}
