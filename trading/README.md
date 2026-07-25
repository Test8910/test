# Trading Dashboard — Phase 1 (Data Collection)

PHP + MySQL pipeline that pulls daily OHLC prices for:

| Symbol   | Index / ETF              | Yahoo symbol |
|----------|--------------------------|--------------|
| QQQ      | Nasdaq-100 (via QQQ)     | `QQQ`        |
| SPX      | S&P 500                  | `^GSPC`      |
| NIFTY50  | Nifty 50                 | `^NSEI`      |
| NIFTY100 | Nifty 100                | `^CNX100`    |

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
- **overbought** ≥ 70  
- **oversold** ≤ 30  
- **neutral** otherwise  

## Next phases (planned)

- Phase 3: Calls vs Puts insight
- Phase 4: fuller dashboard UI (charts, history)
