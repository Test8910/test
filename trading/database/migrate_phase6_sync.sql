-- Phase 6: sync run log + persisted RSI snapshots
USE trading_dashboard;

CREATE TABLE IF NOT EXISTS sync_runs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  started_at DATETIME NOT NULL,
  finished_at DATETIME NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'running',
  symbols_ok INT UNSIGNED NOT NULL DEFAULT 0,
  symbols_failed INT UNSIGNED NOT NULL DEFAULT 0,
  message TEXT NULL,
  PRIMARY KEY (id),
  KEY idx_sync_runs_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rsi_snapshots (
  symbol VARCHAR(20) NOT NULL,
  period INT UNSIGNED NOT NULL DEFAULT 14,
  as_of_date DATE NOT NULL,
  rsi DECIMAL(8, 2) NOT NULL,
  rsi_signal VARCHAR(20) NOT NULL,
  calculated_at DATETIME NOT NULL,
  PRIMARY KEY (symbol, period),
  KEY idx_rsi_as_of (as_of_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
