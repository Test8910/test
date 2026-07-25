# Phase 8 — Future Upgrade Ideas

The dashboard today uses **price-only** logic (OHLC → RSI → smart PUT/CALL/HOLD).  
These upgrades are intentionally deferred until the core loop is stable.

## 1. Real options chain data

Pull live/near-live option chains (strikes, expiries, bid/ask, volume, OI).

**Why:** Replace suggested strikes + heuristic bias with market-traded contracts.

**Likely sources**
- Broker / market-data APIs (paid)
- Delayed free endpoints where available

**Tables (planned)**
```sql
option_contracts (symbol, expiry, strike, right, bid, ask, mid, volume, open_interest, as_of)
```

**Stub:** `Trading\Future\OptionsChainClient`

---

## 2. Implied volatility (IV)

Derive or ingest IV per strike/expiry.

**Why:** High IV → expensive options; IV crush risk after events; compare IV vs realized vol from our price history.

**Planned use**
- Flag “expensive / cheap” premiums
- Prefer credit strategies when IV is rich (later)

**Stub:** `Trading\Future\ImpliedVolatilityService`

---

## 3. Greeks (Delta, Theta, …)

Attach Delta / Theta / Gamma / Vega to contracts or approximate from a pricing model.

**Why**
- **Delta** — directional exposure  
- **Theta** — time decay  
- Filter signals: e.g. only consider calls with delta in a band

**Stub:** `Trading\Future\GreeksCalculator`

---

## 4. Backtesting

Replay historical closes (+ later option paths) through `SmartSignalEngine` and measure outcomes.

**Why:** Know whether PUT/CALL/HOLD rules helped before trusting them live.

**Planned metrics**
- Hit rate of CALL/PUT windows
- Average forward return after signal
- Max drawdown of a simple paper strategy

**Stub:** `Trading\Future\Backtester`

```bash
# future CLI shape
php bin/backtest.php --symbol=QQQ --from=2024-01-01 --to=2025-01-01
```

---

## Suggested build order

1. Backtesting on price + RSI signals (no options API needed)  
2. Options chain ingestion for one market (e.g. US: QQQ/SPY)  
3. IV + basic Delta/Theta  
4. Combine chain + smart signals into position suggestions  

## Non-goals for now

- Live order routing / broker execution  
- Guaranteed P&L advice  
- Full multi-expiry vol surface modeling  
