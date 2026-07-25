-- Historical OHLC price storage + multi-market symbols
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
  sort_order INT NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (symbol),
  KEY idx_symbols_market (market, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Phase 5 multi-market universe
INSERT INTO symbols (symbol, name, yahoo_symbol, market, sort_order, is_active) VALUES
  ('QQQ', 'QQQ — NASDAQ 100', 'QQQ', 'US', 10, 1),
  ('SPY', 'SPY — S&P 500', 'SPY', 'US', 20, 1),
  ('NIFTY50', 'Nifty 50', '^NSEI', 'IN', 10, 1),
  ('NIFTY100', 'Nifty 100', '^CNX100', 'IN', 20, 1),
  ('FTSE100', 'FTSE 100', '^FTSE', 'UK', 10, 1),
  ('NIKKEI225', 'Nikkei 225', '^N225', 'ASIA', 10, 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  yahoo_symbol = VALUES(yahoo_symbol),
  market = VALUES(market),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active);

-- Retire older S&P index ticker in favor of SPY ETF
UPDATE symbols SET is_active = 0, name = 'S&P 500 (legacy index)' WHERE symbol = 'SPX';
