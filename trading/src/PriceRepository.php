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
            'SELECT symbol, name, yahoo_symbol, market, sort_order
             FROM symbols
             WHERE is_active = 1
             ORDER BY FIELD(market, \'US\', \'IN\', \'UK\', \'ASIA\'), sort_order, symbol'
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
     * @return list<array{
     *   symbol: string,
     *   name: ?string,
     *   market: ?string,
     *   bars: int,
     *   first_date: string,
     *   last_date: string,
     *   last_close: string,
     *   prev_close: ?string
     * }>
     */
    public function summary(): array
    {
        $sql = 'SELECT
                  p.symbol,
                  s.name,
                  s.market,
                  s.sort_order,
                  COUNT(*) AS bars,
                  MIN(p.date) AS first_date,
                  MAX(p.date) AS last_date,
                  (
                    SELECT p2.close
                    FROM prices p2
                    WHERE p2.symbol = p.symbol
                    ORDER BY p2.date DESC
                    LIMIT 1
                  ) AS last_close,
                  (
                    SELECT p3.close
                    FROM prices p3
                    WHERE p3.symbol = p.symbol
                    ORDER BY p3.date DESC
                    LIMIT 1 OFFSET 1
                  ) AS prev_close
                FROM prices p
                INNER JOIN symbols s ON s.symbol = p.symbol AND s.is_active = 1
                GROUP BY p.symbol, s.name, s.market, s.sort_order
                ORDER BY FIELD(s.market, \'US\', \'IN\', \'UK\', \'ASIA\'), s.sort_order, p.symbol';

        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * Dashboard rows: price, RSI, trend, signal, options action.
     *
     * @return list<array{
     *   symbol: string,
     *   name: string,
     *   market: string,
     *   bars: int,
     *   first_date: string,
     *   last_date: string,
     *   last_close: string,
     *   prev_close: ?string,
     *   change_pct: ?float,
     *   rsi: ?float,
     *   signal: string,
     *   signal_label: string,
     *   action: string,
     *   trend: string,
     *   trend_label: string
     * }>
     */
    public function summaryWithRsi(int $period = RsiCalculator::DEFAULT_PERIOD): array
    {
        $rows = [];
        foreach ($this->summary() as $row) {
            $closes = $this->closes($row['symbol']);
            $rsi = RsiCalculator::calculateRSI($closes, $period);
            $signal = RsiCalculator::signal($rsi);
            $trend = RsiCalculator::trend($closes);

            $changePct = null;
            if ($row['prev_close'] !== null && (float) $row['prev_close'] != 0.0) {
                $changePct = round(
                    (((float) $row['last_close'] - (float) $row['prev_close']) / (float) $row['prev_close']) * 100,
                    2
                );
            }

            $options = OptionsLogic::analyze((float) $row['last_close'], $rsi, $closes);
            $smart = SmartSignalEngine::evaluate($closes, $rsi, $period);

            $rows[] = [
                ...$row,
                'name' => $row['name'] ?? $row['symbol'],
                'market' => $row['market'] ?? '',
                'change_pct' => $changePct,
                'rsi' => $rsi,
                'signal' => $signal,
                'signal_label' => RsiCalculator::signalLabel($signal),
                'action' => RsiCalculator::action($signal),
                'trend' => $trend,
                'trend_label' => RsiCalculator::trendLabel($trend),
                'options' => $options,
                'smart' => $smart,
            ];
        }

        return $rows;
    }

    public function latestDate(string $symbol): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT MAX(date) AS d FROM prices WHERE symbol = :symbol'
        );
        $stmt->execute([':symbol' => $symbol]);
        $value = $stmt->fetchColumn();

        return $value === false || $value === null ? null : (string) $value;
    }

    public function upsertRsiSnapshot(
        string $symbol,
        int $period,
        string $asOfDate,
        float $rsi,
        string $signal
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rsi_snapshots (symbol, period, as_of_date, rsi, rsi_signal, calculated_at)
             VALUES (:symbol, :period, :as_of_date, :rsi, :rsi_signal, NOW())
             ON DUPLICATE KEY UPDATE
               as_of_date = VALUES(as_of_date),
               rsi = VALUES(rsi),
               rsi_signal = VALUES(rsi_signal),
               calculated_at = VALUES(calculated_at)'
        );
        $stmt->execute([
            ':symbol' => $symbol,
            ':period' => $period,
            ':as_of_date' => $asOfDate,
            ':rsi' => $rsi,
            ':rsi_signal' => $signal,
        ]);
    }

    /**
     * @param array{
     *   signal: string,
     *   confidence: string,
     *   score: float,
     *   rsi: ?float,
     *   trend: string,
     *   change_pct: ?float,
     *   reasons: list<string>
     * } $smart
     */
    public function upsertSmartSignal(string $symbol, string $asOfDate, array $smart): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO smart_signals
               (symbol, smart_signal, confidence, score, rsi, trend, change_pct, reasons, as_of_date, calculated_at)
             VALUES
               (:symbol, :smart_signal, :confidence, :score, :rsi, :trend, :change_pct, :reasons, :as_of_date, NOW())
             ON DUPLICATE KEY UPDATE
               smart_signal = VALUES(smart_signal),
               confidence = VALUES(confidence),
               score = VALUES(score),
               rsi = VALUES(rsi),
               trend = VALUES(trend),
               change_pct = VALUES(change_pct),
               reasons = VALUES(reasons),
               as_of_date = VALUES(as_of_date),
               calculated_at = VALUES(calculated_at)'
        );
        $stmt->execute([
            ':symbol' => $symbol,
            ':smart_signal' => $smart['signal'],
            ':confidence' => $smart['confidence'],
            ':score' => $smart['score'],
            ':rsi' => $smart['rsi'],
            ':trend' => $smart['trend'],
            ':change_pct' => $smart['change_pct'],
            ':reasons' => implode(' | ', $smart['reasons']),
            ':as_of_date' => $asOfDate,
        ]);
    }

    /**
     * @return array{
     *   id: int,
     *   started_at: string,
     *   finished_at: ?string,
     *   status: string,
     *   symbols_ok: int,
     *   symbols_failed: int,
     *   message: ?string
     * }|null
     */
    public function latestSyncRun(): ?array
    {
        $stmt = $this->pdo->query(
            'SELECT id, started_at, finished_at, status, symbols_ok, symbols_failed, message
             FROM sync_runs
             ORDER BY id DESC
             LIMIT 1'
        );
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
