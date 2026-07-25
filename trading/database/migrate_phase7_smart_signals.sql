-- Phase 7: persisted smart signal engine output
USE trading_dashboard;

CREATE TABLE IF NOT EXISTS smart_signals (
  symbol VARCHAR(20) NOT NULL,
  smart_signal VARCHAR(10) NOT NULL,
  confidence VARCHAR(10) NOT NULL,
  score DECIMAL(8, 2) NOT NULL,
  rsi DECIMAL(8, 2) NULL,
  trend VARCHAR(10) NOT NULL,
  change_pct DECIMAL(8, 2) NULL,
  reasons TEXT NULL,
  as_of_date DATE NOT NULL,
  calculated_at DATETIME NOT NULL,
  PRIMARY KEY (symbol),
  KEY idx_smart_signal (smart_signal, confidence)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
