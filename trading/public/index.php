<?php

declare(strict_types=1);

/**
 * Phase 1+2 dashboard: OHLC summary, RSI(14), basic signals.
 */

use Trading\Database;
use Trading\PriceRepository;
use Trading\RsiCalculator;

$config = require dirname(__DIR__) . '/src/bootstrap.php';

try {
    $repo = new PriceRepository(Database::connection($config));
    $summary = $repo->summaryWithRsi(RsiCalculator::DEFAULT_PERIOD);
    $error = null;
} catch (Throwable $e) {
    $summary = [];
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Trading Dashboard — Phase 2</title>
  <style>
    :root {
      --bg: #0f1419;
      --panel: #1a222c;
      --text: #e8eef4;
      --muted: #93a4b5;
      --accent: #3dd68c;
      --line: #2a3542;
      --hot: #ff7b72;
      --cold: #79c0ff;
      --mid: #d2a8ff;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: "Segoe UI", system-ui, sans-serif;
      background: radial-gradient(circle at top, #1b2733, var(--bg));
      color: var(--text);
      min-height: 100vh;
      padding: 2rem 1.25rem;
    }
    main { max-width: 960px; margin: 0 auto; }
    h1 { margin: 0 0 0.35rem; font-size: 1.75rem; font-weight: 650; }
    p { color: var(--muted); margin: 0 0 1.5rem; }
    .error {
      background: #3a1d1d;
      border: 1px solid #7a3030;
      color: #ffb4b4;
      padding: 0.9rem 1rem;
      border-radius: 8px;
      margin-bottom: 1rem;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background: var(--panel);
      border-radius: 10px;
      overflow: hidden;
    }
    th, td {
      text-align: left;
      padding: 0.85rem 1rem;
      border-bottom: 1px solid var(--line);
      font-size: 0.95rem;
    }
    th { color: var(--muted); font-weight: 600; }
    tr:last-child td { border-bottom: 0; }
    .close, .rsi { font-variant-numeric: tabular-nums; }
    .close { color: var(--accent); }
    .signal {
      display: inline-block;
      padding: 0.2rem 0.55rem;
      border-radius: 999px;
      font-size: 0.8rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }
    .signal-overbought { background: rgba(255, 123, 114, 0.15); color: var(--hot); }
    .signal-oversold { background: rgba(121, 192, 255, 0.15); color: var(--cold); }
    .signal-neutral { background: rgba(210, 168, 255, 0.12); color: var(--mid); }
    .signal-na { background: rgba(147, 164, 181, 0.12); color: var(--muted); }
    .empty { color: var(--muted); padding: 1.25rem; background: var(--panel); border-radius: 10px; }
    code { color: #9fd3ff; }
    .legend { margin-top: 1rem; font-size: 0.85rem; color: var(--muted); }
  </style>
</head>
<body>
  <main>
    <h1>Trading Dashboard</h1>
    <p>Phase 2 — RSI(<?= (int) RsiCalculator::DEFAULT_PERIOD ?>) from close prices + basic signals.</p>

    <?php if ($error !== null): ?>
      <div class="error">Database error: <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <p>Run <code>php bin/setup_db.php</code> then <code>php bin/ingest_prices.php</code>.</p>
    <?php elseif ($summary === []): ?>
      <div class="empty">No prices yet. Run <code>php bin/ingest_prices.php</code>.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Symbol</th>
            <th>Bars</th>
            <th>To</th>
            <th>Last close</th>
            <th>RSI(14)</th>
            <th>Signal</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($summary as $row): ?>
            <?php
              $signal = $row['signal'];
              $signalClass = match ($signal) {
                  'overbought' => 'signal-overbought',
                  'oversold' => 'signal-oversold',
                  'neutral' => 'signal-neutral',
                  default => 'signal-na',
              };
            ?>
            <tr>
              <td><?= htmlspecialchars($row['symbol'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= (int) $row['bars'] ?></td>
              <td><?= htmlspecialchars($row['last_date'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="close"><?= htmlspecialchars((string) $row['last_close'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="rsi">
                <?= $row['rsi'] === null ? 'n/a' : htmlspecialchars(number_format((float) $row['rsi'], 2), ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td><span class="signal <?= $signalClass ?>"><?= htmlspecialchars($signal, ENT_QUOTES, 'UTF-8') ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="legend">
        Overbought ≥ <?= (int) RsiCalculator::OVERBOUGHT ?>
        · Oversold ≤ <?= (int) RsiCalculator::OVERSOLD ?>
        · otherwise Neutral
      </p>
    <?php endif; ?>
  </main>
</body>
</html>
