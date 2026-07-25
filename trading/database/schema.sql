-- Phase 1: historical OHLC price storage
CREATE DATABASE IF NOT EXISTS trading_dashboard
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE trading_dashboard;

CREATE TABLE IF NOT EXISTS prices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  symbol VARCHAR(20) NOT NULL,
  date DATE NOT NULL,
  open DECIMAL(12, 4) NULL,
  high DECIMAL(12, 4) NULL,
  low DECIMAL(12, 4) NULL,
  close DECIMAL(12, 2) NOT NULL,
  volume BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_prices_symbol_date (symbol, date),
  KEY idx_prices_symbol_date (symbol, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS symbols (
  symbol VARCHAR(20) NOT NULL,
  name VARCHAR(100) NOT NULL,
  yahoo_symbol VARCHAR(30) NOT NULL,
  market VARCHAR(20) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (symbol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO symbols (symbol, name, yahoo_symbol, market) VALUES
  ('QQQ', 'Invesco QQQ (Nasdaq-100)', 'QQQ', 'US'),
  ('SPX', 'S&P 500', '^GSPC', 'US'),
  ('NIFTY50', 'Nifty 50', '^NSEI', 'IN'),
  ('NIFTY100', 'Nifty 100', '^CNX100', 'IN')
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  yahoo_symbol = VALUES(yahoo_symbol),
  market = VALUES(market),
  is_active = 1;
