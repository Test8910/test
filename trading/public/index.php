<?php

declare(strict_types=1);

/**
 * Multi-market dashboard: US / India / UK / Asia.
 */

use Trading\Database;
use Trading\Markets;
use Trading\PriceRepository;
use Trading\RsiCalculator;

$config = require dirname(__DIR__) . '/src/bootstrap.php';

try {
    $repo = new PriceRepository(Database::connection($config));
    $assets = $repo->summaryWithRsi(RsiCalculator::DEFAULT_PERIOD);
    $lastSync = $repo->latestSyncRun();
    $error = null;
} catch (Throwable $e) {
    $assets = [];
    $lastSync = null;
    $error = $e->getMessage();
}

$grouped = [];
foreach ($assets as $asset) {
    $market = $asset['market'] !== '' ? $asset['market'] : 'OTHER';
    $grouped[$market][] = $asset;
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

    .sync-bar {
      margin-top: 0.9rem;
      display: inline-flex;
      flex-wrap: wrap;
      gap: 0.55rem 0.85rem;
      align-items: center;
      padding: 0.55rem 0.8rem;
      border-radius: 999px;
      background: rgba(0, 0, 0, 0.22);
      border: 1px solid var(--line);
      color: var(--muted);
      font-size: 0.82rem;
    }
    .sync-ok { color: var(--up); font-weight: 700; }
    .sync-error { color: var(--down); font-weight: 700; }
    .sync-partial, .sync-running { color: var(--neutral); font-weight: 700; }

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

    .market {
      margin-bottom: 1.75rem;
    }

    .market-title {
      display: flex;
      align-items: baseline;
      gap: 0.55rem;
      margin: 0 0 0.85rem;
      font-size: 1.15rem;
      font-weight: 700;
    }

    .market-title span {
      color: var(--muted);
      font-size: 0.85rem;
      font-weight: 500;
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

    .options {
      display: grid;
      gap: 0.45rem;
      padding: 0.85rem 0.9rem;
      border-radius: 10px;
      background: rgba(0, 0, 0, 0.18);
      border: 1px solid var(--line);
      font-size: 0.9rem;
    }

    .options .strike {
      font-weight: 700;
      letter-spacing: -0.01em;
    }

    .bias-row {
      display: flex;
      justify-content: space-between;
      gap: 0.5rem;
      color: var(--muted);
    }

    .bias-row strong { color: var(--text); font-weight: 700; }
    .bias-strong { color: var(--up); }
    .bias-moderate { color: var(--neutral); }
    .bias-weak { color: var(--down); }

    .hint {
      margin: 0.15rem 0 0;
      color: var(--muted);
      font-size: 0.78rem;
      line-height: 1.4;
    }

    .smart {
      padding: 0.85rem 0.9rem;
      border-radius: 10px;
      border: 1px solid var(--line);
      background: rgba(121, 192, 255, 0.08);
    }

    .smart-top {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 0.45rem;
    }

    .smart-badge {
      font-size: 1rem;
      font-weight: 800;
      letter-spacing: 0.04em;
      padding: 0.28rem 0.65rem;
      border-radius: 8px;
    }
    .smart-PUT { background: rgba(255, 159, 107, 0.2); color: var(--put); }
    .smart-CALL { background: rgba(121, 192, 255, 0.2); color: var(--call); }
    .smart-HOLD { background: rgba(210, 168, 255, 0.16); color: var(--neutral); }

    .smart-conf {
      color: var(--muted);
      font-size: 0.8rem;
      font-weight: 600;
      text-transform: uppercase;
    }

    .smart-reasons {
      margin: 0;
      padding-left: 1.1rem;
      color: var(--muted);
      font-size: 0.8rem;
      line-height: 1.45;
    }

    .meta {
      color: var(--muted);
      font-size: 0.78rem;
    }

    .legend {
      margin-top: 0.5rem;
      color: var(--muted);
      font-size: 0.85rem;
      line-height: 1.5;
    }

    .roadmap {
      margin-top: 2rem;
      padding: 1.15rem 1.2rem;
      border-radius: 14px;
      border: 1px dashed var(--line);
      background: rgba(0, 0, 0, 0.16);
    }

    .roadmap h2 {
      margin: 0 0 0.45rem;
      font-size: 1.05rem;
    }

    .roadmap p {
      margin: 0 0 0.75rem;
      color: var(--muted);
      font-size: 0.9rem;
    }

    .roadmap ul {
      margin: 0;
      padding-left: 1.15rem;
      color: var(--muted);
      font-size: 0.9rem;
      line-height: 1.55;
    }

    .roadmap li strong { color: var(--text); }

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
        EOD sync at 4:00 PM America/New_York, Mon–Fri (skips weekends &amp; US holidays).
      </p>
      <?php if ($lastSync !== null): ?>
        <?php
          $syncClass = match ($lastSync['status']) {
              'ok' => 'sync-ok',
              'error' => 'sync-error',
              'partial' => 'sync-partial',
              default => 'sync-running',
          };
          $syncTime = $lastSync['finished_at'] ?? $lastSync['started_at'];
        ?>
        <div class="sync-bar">
          <span>Last sync:</span>
          <strong><?= htmlspecialchars((string) $syncTime, ENT_QUOTES, 'UTF-8') ?></strong>
          <span class="<?= $syncClass ?>"><?= htmlspecialchars(strtoupper((string) $lastSync['status']), ENT_QUOTES, 'UTF-8') ?></span>
          <span>
            <?= (int) $lastSync['symbols_ok'] ?> ok
            <?php if ((int) $lastSync['symbols_failed'] > 0): ?>
              · <?= (int) $lastSync['symbols_failed'] ?> failed
            <?php endif; ?>
          </span>
        </div>
      <?php else: ?>
        <div class="sync-bar">
          No sync yet — cron at 4:00 PM ET Mon–Fri, or run <code>php update_prices.php --force</code>.
        </div>
      <?php endif; ?>
    </header>

    <?php if ($error !== null): ?>
      <div class="error">Database error: <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <p class="subtitle">Run <code>php bin/setup_db.php</code> then <code>php bin/ingest_prices.php</code>.</p>
    <?php elseif ($assets === []): ?>
      <div class="empty">No prices yet. Run <code>php bin/ingest_prices.php</code>.</div>
    <?php else: ?>
      <?php foreach (Markets::order() as $marketCode): ?>
        <?php if (empty($grouped[$marketCode])) { continue; } ?>
        <section class="market" aria-label="<?= htmlspecialchars(Markets::label($marketCode), ENT_QUOTES, 'UTF-8') ?>">
          <h2 class="market-title">
            <?= Markets::flag($marketCode) ?>
            <?= htmlspecialchars(Markets::label($marketCode), ENT_QUOTES, 'UTF-8') ?>
            <span><?= count($grouped[$marketCode]) ?> assets</span>
          </h2>
          <div class="grid">
            <?php foreach ($grouped[$marketCode] as $asset): ?>
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
                $opt = $asset['options'];
                $callStrengthClass = 'bias-' . strtolower($opt['call']['strength']);
                $putStrengthClass = 'bias-' . strtolower($opt['put']['strength']);
                $smart = $asset['smart'];
                $smartClass = 'smart-' . preg_replace('/[^A-Z]/', '', $smart['signal']);
              ?>
              <article class="asset">
                <div class="asset-top">
                  <div>
                    <h3 class="symbol"><?= htmlspecialchars($asset['symbol'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="name"><?= htmlspecialchars($asset['name'], ENT_QUOTES, 'UTF-8') ?></p>
                  </div>
                  <span class="trend <?= htmlspecialchars($trendClass, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($asset['trend_label'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </div>

                <div class="price-row">
                  <div class="price"><?= htmlspecialchars(Markets::formatPrice($asset['market'], $asset['last_close']), ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="change <?= $changeClass ?>"><?= htmlspecialchars($changeText, ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div class="smart">
                  <div class="smart-top">
                    <span class="smart-badge <?= htmlspecialchars($smartClass, ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars($smart['signal'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span class="smart-conf"><?= htmlspecialchars($smart['confidence'], ENT_QUOTES, 'UTF-8') ?> confidence</span>
                  </div>
                  <ul class="smart-reasons">
                    <?php foreach (array_slice($smart['reasons'], 0, 3) as $reason): ?>
                      <li><?= htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>

                <p class="insight">
                  RSI: <strong><?= htmlspecialchars($rsiText, ENT_QUOTES, 'UTF-8') ?></strong>
                  → <?= htmlspecialchars($asset['signal_label'], ENT_QUOTES, 'UTF-8') ?>
                  → <span class="<?= $actionClass ?>"><?= htmlspecialchars($asset['action'], ENT_QUOTES, 'UTF-8') ?></span>
                </p>

                <div class="options">
                  <div class="strike">
                    Strike: <?= htmlspecialchars(Markets::formatPrice($asset['market'], $opt['strike']), ENT_QUOTES, 'UTF-8') ?>
                  </div>
                  <div class="bias-row">
                    <span>Call Bias:</span>
                    <strong class="<?= htmlspecialchars($callStrengthClass, ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars($opt['call']['strength'], ENT_QUOTES, 'UTF-8') ?>
                      (<?= htmlspecialchars($opt['call']['note'], ENT_QUOTES, 'UTF-8') ?>)
                    </strong>
                  </div>
                  <div class="bias-row">
                    <span>Put Bias:</span>
                    <strong class="<?= htmlspecialchars($putStrengthClass, ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars($opt['put']['strength'], ENT_QUOTES, 'UTF-8') ?>
                      (<?= htmlspecialchars($opt['put']['note'], ENT_QUOTES, 'UTF-8') ?>)
                    </strong>
                  </div>
                  <p class="hint">
                    Call = price ABOVE strike · Put = price BELOW strike
                    <?php if ($opt['rsi_bias'] !== 'NONE'): ?>
                      · RSI tilt: <?= htmlspecialchars($opt['rsi_bias'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                  </p>
                </div>

                <div class="meta">As of <?= htmlspecialchars($asset['last_date'], ENT_QUOTES, 'UTF-8') ?></div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>

      <p class="legend">
        Smart Signal: RSI &gt; 70 → PUT · RSI &lt; 30 → CALL · else HOLD,
        then adjusted by price movement + short-term market trend.
      </p>

      <section class="roadmap" aria-label="Phase 8 future upgrades">
        <h2>Phase 8 — Future upgrades</h2>
        <p>Not built yet. Planned next after the price-only engine is solid:</p>
        <ul>
          <li><strong>Real options chain data</strong> — live strikes, expiries, bid/ask</li>
          <li><strong>Implied volatility</strong> — expensive vs cheap premium context</li>
          <li><strong>Greeks</strong> — Delta, Theta (and later Gamma/Vega)</li>
          <li><strong>Backtesting</strong> — replay smart signals on history</li>
        </ul>
        <p style="margin-top:0.75rem;margin-bottom:0;">Details: <code>trading/PHASE8_ROADMAP.md</code></p>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>
