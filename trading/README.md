# Trading Dashboard — Phase 1 (Data Collection)

PHP + MySQL pipeline that pulls daily OHLC prices for:

| Region | Symbol    | Index / ETF        | Yahoo symbol |
|--------|-----------|--------------------|--------------|
| 🇺🇸 US | QQQ       | NASDAQ 100         | `QQQ`        |
| 🇺🇸 US | SPY       | S&P 500            | `SPY`        |
| 🇮🇳 India | NIFTY50 | Nifty 50           | `^NSEI`      |
| 🇮🇳 India | NIFTY100| Nifty 100          | `^CNX100`    |
| 🇬🇧 UK | FTSE100   | FTSE 100           | `^FTSE`      |
| 🌏 Asia | NIKKEI225 | Nikkei 225       | `^N225`      |

Data source: Yahoo Finance chart API (unofficial, free).

## Requirements

- PHP 8.1+ with extensions: `pdo_mysql`, `curl`, `json`
- MySQL 8+ or MariaDB 10.5+

## Setup

```bash
cd trading
cp config/config.example.php config/config.php
# edit DB credentials in config/config.php

# create DB + tables + symbol seed
mysql -u root -p < database/schema.sql
# or, if the app user can create databases:
php bin/setup_db.php

# fetch ~1y of daily OHLC into `prices`
php bin/ingest_prices.php
```

Optional flags:

```bash
php bin/ingest_prices.php --symbol=QQQ
php bin/ingest_prices.php --range=6mo
```

## Schema

`prices` stores OHLC (close is required; open/high/low/volume when available):

```sql
prices (
  id INT,
  symbol VARCHAR(20),
  date DATE,
  open DECIMAL(12,4),
  high DECIMAL(12,4),
  low DECIMAL(12,4),
  close DECIMAL(12,2),
  volume BIGINT
)
```

Unique key on `(symbol, date)` so re-running ingest upserts safely.

## Status page

```bash
cd trading/public
php -S 127.0.0.1:8081
# open http://127.0.0.1:8081
```

## Phase 2 — RSI

Compute RSI(14) from stored closes only:

```bash
php bin/calculate_rsi.php
php bin/calculate_rsi.php --symbol=QQQ
```

Core logic lives in `src/RsiCalculator.php`:
1. Price changes → gains / losses  
2. Seed averages over the first 14 changes (SMA)  
3. RS = avgGain / avgLoss → RSI  
4. Later bars use Wilder smoothing (standard RSI)

Dashboard shows RSI plus basic signals:
- **overbought** ≥ 70 → Consider PUT  
- **oversold** ≤ 30 → Consider CALL  
- **neutral** otherwise → Wait / Hold  

## Phase 3 — Dashboard UI

`public/index.php` shows one card per asset with:
- Current price (+ daily %)
- RSI value
- Trend (up / down)
- Signal → CALL/PUT style action

```bash
cd trading/public
php -S 127.0.0.1:8081
```

## Phase 4 — Options bias (no pricing)

Simple logic (no option chain / Greeks):

- RSI > 70 → market high → **PUT bias**
- RSI < 30 → market low → **CALL bias**
- Suggest a nearby strike above spot
- Score Call vs Put strength from distance vs a rough realistic move

```bash
php bin/options_bias.php
php bin/options_bias.php --price=785 --strike=792 --rsi=72
```

Example output idea:

```
Price  = 785
Strike = 792
Call Bias: Weak (far OTM)
Put Bias:  Strong (closer to realistic move)
```

## Phase 5 — Multi-market tracking

```bash
# existing DB: add sort_order (skip if already present), then refresh symbols
mysql -u root -p -e "ALTER TABLE trading_dashboard.symbols ADD COLUMN sort_order INT NOT NULL DEFAULT 100 AFTER market;"
mysql -u root -p < database/migrate_phase5_symbols.sql
php bin/ingest_prices.php
```

Dashboard groups assets by region (US / India / UK / Asia).

## Phase 6 — Synchronization (cron)

`update_prices.php` keeps markets fresh:

1. Fetch latest prices  
2. Store in MySQL  
3. Recalculate RSI snapshots  

```bash
# one-time schema bits (existing DB)
mysql -u root -p < database/migrate_phase6_sync.sql

# run once
php update_prices.php

# cron every 5 minutes
crontab -e
# add:
# every 5 min -> /usr/bin/php /ABS/PATH/TO/trading/update_prices.php >> /ABS/PATH/TO/trading/storage/sync.log 2>&1
# crontab timing field: (star)/5 * * * *
```

Sample crontab file: `cron/trading-dashboard`

The dashboard header shows the last sync status.

## Next (optional)

- Charts / RSI history
- Real option-chain integration later
