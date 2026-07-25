<?php

declare(strict_types=1);

namespace Trading;

use PDO;
use Throwable;

/**
 * Phase 6 synchronization:
 * fetch latest prices → store in DB → recalculate RSI snapshots.
 */
final class PriceSync
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PriceRepository $repo,
        private readonly YahooFinanceClient $client,
        private readonly int $delayMs = 400,
    ) {
    }

    /**
     * @return array{
     *   run_id: int,
     *   status: string,
     *   symbols_ok: int,
     *   symbols_failed: int,
     *   results: list<array{symbol: string, ok: bool, bars?: int, rsi?: ?float, signal?: string, error?: string}>
     * }
     */
    public function run(string $range = '1mo', int $rsiPeriod = RsiCalculator::DEFAULT_PERIOD): array
    {
        $runId = $this->beginRun();
        $ok = 0;
        $failed = 0;
        $results = [];
        $errors = [];

        $symbols = $this->repo->activeSymbols();
        foreach ($symbols as $index => $symbolRow) {
            $symbol = $symbolRow['symbol'];
            $yahoo = $symbolRow['yahoo_symbol'];

            try {
                $rows = $this->client->fetchDailyOhlc($yahoo, $range);
                $bars = $this->repo->upsertPrices($symbol, $rows);
                $closes = $this->repo->closes($symbol);
                $rsi = RsiCalculator::calculateRSI($closes, $rsiPeriod);
                $signal = RsiCalculator::signal($rsi);

                if ($rsi !== null && $closes !== []) {
                    $asOf = $this->repo->latestDate($symbol) ?? gmdate('Y-m-d');
                    $this->repo->upsertRsiSnapshot($symbol, $rsiPeriod, $asOf, $rsi, $signal);
                }

                $ok++;
                $results[] = [
                    'symbol' => $symbol,
                    'ok' => true,
                    'bars' => $bars,
                    'rsi' => $rsi,
                    'signal' => $signal,
                ];
            } catch (Throwable $e) {
                $failed++;
                $errors[] = "{$symbol}: " . $e->getMessage();
                $results[] = [
                    'symbol' => $symbol,
                    'ok' => false,
                    'error' => $e->getMessage(),
                ];
            }

            if ($index < count($symbols) - 1 && $this->delayMs > 0) {
                usleep($this->delayMs * 1000);
            }
        }

        $status = $failed === 0 ? 'ok' : ($ok === 0 ? 'error' : 'partial');
        $message = $errors === [] ? 'Sync completed' : implode('; ', $errors);
        $this->finishRun($runId, $status, $ok, $failed, $message);

        return [
            'run_id' => $runId,
            'status' => $status,
            'symbols_ok' => $ok,
            'symbols_failed' => $failed,
            'results' => $results,
        ];
    }

    private function beginRun(): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sync_runs (started_at, status) VALUES (NOW(), :status)'
        );
        $stmt->execute([':status' => 'running']);

        return (int) $this->pdo->lastInsertId();
    }

    private function finishRun(int $runId, string $status, int $ok, int $failed, string $message): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE sync_runs
             SET finished_at = NOW(),
                 status = :status,
                 symbols_ok = :ok,
                 symbols_failed = :failed,
                 message = :message
             WHERE id = :id'
        );
        $stmt->execute([
            ':status' => $status,
            ':ok' => $ok,
            ':failed' => $failed,
            ':message' => $message,
            ':id' => $runId,
        ]);
    }
}
