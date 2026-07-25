<?php

declare(strict_types=1);

/**
 * Phase 3 dashboard UI: price, RSI, trend, signal + CALL/PUT action.
 */

use Trading\Database;
use Trading\PriceRepository;
use Trading\RsiCalculator;

$config = require dirname(__DIR__) . '/src/bootstrap.php';

try {
    $repo = new PriceRepository(Database::connection($config));
    $assets = $repo->summaryWithRsi(RsiCalculator::DEFAULT_PERIOD);
    $error = null;
} catch (Throwable $e) {
    $assets = [];
    $error = $e->getMessage();
}

function format_price(string $market, float|string $price): string
{
    $value = (float) $price;
    if (strtoupper($market) === 'IN') {
        return '₹' . number_format($value, 2);
    }

    return '$' . number_format($value, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Trading Dashboard</title>
  <style>
    :root {
      --bg: #0e151c;
      --panel: #16212b;
      --panel-2: #1c2a36;
      --text: #eaf1f7;
      --muted: #8fa3b5;
      --line: #2a3a49;
      --up: #3dd68c;
      --down: #ff7b72;
      --call: #79c0ff;
      --put: #ff9f6b;
      --neutral: #d2a8ff;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      min-height: 100vh;
      font-family: "Segoe UI", system-ui, sans-serif;
      color: var(--text);
      background:
        radial-gradient(1000px 500px at 10% -10%, rgba(61, 214, 140, 0.12), transparent 55%),
        radial-gradient(900px 480px at 100% 0%, rgba(121, 192, 255, 0.12), transparent 50%),
        var(--bg);
      padding: 2rem 1.25rem 3rem;
    }

    main { max-width: 1100px; margin: 0 auto; }

    header { margin-bottom: 1.75rem; }
    h1 {
      margin: 0 0 0.4rem;
      font-size: clamp(1.6rem, 3vw, 2.1rem);
      font-weight: 700;
      letter-spacing: -0.02em;
    }
    .subtitle { margin: 0; color: var(--muted); line-height: 1.5; }

    .error {
      background: #3a1d1d;
      border: 1px solid #7a3030;
      color: #ffb4b4;
      padding: 0.9rem 1rem;
      border-radius: 10px;
      margin-bottom: 1rem;
    }

    .empty {
      color: var(--muted);
      padding: 1.25rem;
      background: var(--panel);
      border-radius: 12px;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1rem;
    }

    .asset {
      background: linear-gradient(180deg, var(--panel-2), var(--panel));
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 1.15rem 1.2rem 1.25rem;
      display: flex;
      flex-direction: column;
      gap: 0.85rem;
      animation: rise 0.45s ease both;
    }

    .asset:nth-child(2) { animation-delay: 0.05s; }
    .asset:nth-child(3) { animation-delay: 0.1s; }
    .asset:nth-child(4) { animation-delay: 0.15s; }

    @keyframes rise {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .asset-top {
      display: flex;
      justify-content: space-between;
      gap: 0.75rem;
      align-items: flex-start;
    }

    .symbol {
      margin: 0;
      font-size: 1.35rem;
      font-weight: 700;
      letter-spacing: -0.02em;
    }

    .name {
      margin: 0.2rem 0 0;
      color: var(--muted);
      font-size: 0.85rem;
    }

    .trend {
      font-size: 0.8rem;
      font-weight: 700;
      padding: 0.28rem 0.55rem;
      border-radius: 999px;
      white-space: nowrap;
    }
    .trend-up { background: rgba(61, 214, 140, 0.14); color: var(--up); }
    .trend-down { background: rgba(255, 123, 114, 0.14); color: var(--down); }
    .trend-flat, .trend-na { background: rgba(143, 163, 181, 0.14); color: var(--muted); }

    .price-row {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      gap: 0.75rem;
    }

    .price {
      font-size: 1.55rem;
      font-weight: 700;
      font-variant-numeric: tabular-nums;
      letter-spacing: -0.02em;
    }

    .change {
      font-size: 0.9rem;
      font-variant-numeric: tabular-nums;
      font-weight: 600;
    }
    .change-up { color: var(--up); }
    .change-down { color: var(--down); }
    .change-flat { color: var(--muted); }

    .insight {
      margin: 0;
      padding: 0.85rem 0.9rem;
      border-radius: 10px;
      background: rgba(0, 0, 0, 0.22);
      border: 1px solid var(--line);
      line-height: 1.45;
      font-size: 0.95rem;
    }

    .insight strong { font-weight: 700; }
    .action-put { color: var(--put); font-weight: 700; }
    .action-call { color: var(--call); font-weight: 700; }
    .action-wait { color: var(--neutral); font-weight: 700; }

    .meta {
      color: var(--muted);
      font-size: 0.78rem;
    }

    .legend {
      margin-top: 1.25rem;
      color: var(--muted);
      font-size: 0.85rem;
      line-height: 1.5;
    }

    code { color: #9fd3ff; }

    @media (prefers-reduced-motion: reduce) {
      .asset { animation: none; }
    }
  </style>
</head>
<body>
  <main>
    <header>
      <h1>Trading Dashboard</h1>
      <p class="subtitle">
        Phase 3 — price, RSI(<?= (int) RsiCalculator::DEFAULT_PERIOD ?>), trend, and CALL/PUT style signals.
      </p>
    </header>

    <?php if ($error !== null): ?>
      <div class="error">Database error: <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <p class="subtitle">Run <code>php bin/setup_db.php</code> then <code>php bin/ingest_prices.php</code>.</p>
    <?php elseif ($assets === []): ?>
      <div class="empty">No prices yet. Run <code>php bin/ingest_prices.php</code>.</div>
    <?php else: ?>
      <section class="grid" aria-label="Asset signals">
        <?php foreach ($assets as $asset): ?>
          <?php
            $trendClass = 'trend-' . preg_replace('/[^a-z]/', '', $asset['trend']);
            $change = $asset['change_pct'];
            $changeClass = 'change-flat';
            $changeText = '—';
            if ($change !== null) {
                $changeClass = $change > 0 ? 'change-up' : ($change < 0 ? 'change-down' : 'change-flat');
                $changeText = ($change > 0 ? '+' : '') . number_format($change, 2) . '%';
            }
            $actionClass = match ($asset['signal']) {
                'overbought' => 'action-put',
                'oversold' => 'action-call',
                default => 'action-wait',
            };
            $rsiText = $asset['rsi'] === null ? 'n/a' : number_format((float) $asset['rsi'], 2);
          ?>
          <article class="asset">
            <div class="asset-top">
              <div>
                <h2 class="symbol"><?= htmlspecialchars($asset['symbol'], ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="name"><?= htmlspecialchars($asset['name'], ENT_QUOTES, 'UTF-8') ?></p>
              </div>
              <span class="trend <?= htmlspecialchars($trendClass, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($asset['trend_label'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>

            <div class="price-row">
              <div class="price"><?= htmlspecialchars(format_price($asset['market'], $asset['last_close']), ENT_QUOTES, 'UTF-8') ?></div>
              <div class="change <?= $changeClass ?>"><?= htmlspecialchars($changeText, ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <p class="insight">
              RSI: <strong><?= htmlspecialchars($rsiText, ENT_QUOTES, 'UTF-8') ?></strong>
              → <?= htmlspecialchars($asset['signal_label'], ENT_QUOTES, 'UTF-8') ?>
              → <span class="<?= $actionClass ?>"><?= htmlspecialchars($asset['action'], ENT_QUOTES, 'UTF-8') ?></span>
            </p>

            <div class="meta">As of <?= htmlspecialchars($asset['last_date'], ENT_QUOTES, 'UTF-8') ?></div>
          </article>
        <?php endforeach; ?>
      </section>

      <p class="legend">
        RSI ≥ <?= (int) RsiCalculator::OVERBOUGHT ?> → Overbought → Consider PUT ·
        RSI ≤ <?= (int) RsiCalculator::OVERSOLD ?> → Oversold → Consider CALL ·
        otherwise Wait / Hold
      </p>
    <?php endif; ?>
  </main>
</body>
</html>
