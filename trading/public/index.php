<?php

declare(strict_types=1);

/**
 * Minimal Phase 1 status page: show ingested symbols and latest closes.
 */

use Trading\Database;
use Trading\PriceRepository;

$config = require dirname(__DIR__) . '/src/bootstrap.php';

try {
    $repo = new PriceRepository(Database::connection($config));
    $summary = $repo->summary();
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
  <title>Trading Dashboard — Phase 1</title>
  <style>
    :root {
      --bg: #0f1419;
      --panel: #1a222c;
      --text: #e8eef4;
      --muted: #93a4b5;
      --accent: #3dd68c;
      --line: #2a3542;
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
    main { max-width: 820px; margin: 0 auto; }
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
    .close { color: var(--accent); font-variant-numeric: tabular-nums; }
    .empty { color: var(--muted); padding: 1.25rem; background: var(--panel); border-radius: 10px; }
    code { color: #9fd3ff; }
  </style>
</head>
<body>
  <main>
    <h1>Trading Dashboard</h1>
    <p>Phase 1 — historical price data collection (OHLC in MySQL).</p>

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
            <th>From</th>
            <th>To</th>
            <th>Last close</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($summary as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['symbol'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= (int) $row['bars'] ?></td>
              <td><?= htmlspecialchars($row['first_date'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($row['last_date'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="close"><?= htmlspecialchars((string) $row['last_close'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </main>
</body>
</html>
