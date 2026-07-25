-- Apply Phase 5 symbol universe on an existing DB
USE trading_dashboard;

-- Add sort_order if missing (ignore error if it already exists)
-- Run: ALTER TABLE symbols ADD COLUMN sort_order INT NOT NULL DEFAULT 100 AFTER market;

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

UPDATE symbols SET is_active = 0, name = 'S&P 500 (legacy index)' WHERE symbol = 'SPX';
