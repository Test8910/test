# test

## Trading Dashboard (PHP)

Code lives in [`trading/`](trading/):

- **Phase 1:** Fetch daily OHLC into MySQL
- **Phase 2:** RSI(14) + overbought/oversold signals
- **Phase 3:** Dashboard UI — price, RSI, trend, CALL/PUT actions
- **Phase 4:** Options bias — strike + Call/Put strength
- **Phase 5:** Multi-market — US, India, UK, Asia
- **Phase 6:** Cron sync — `update_prices.php` every 5 minutes
- **Phase 7:** Smart Signal Engine — RSI + move + trend → PUT/CALL/HOLD
- **Phase 8:** Future upgrades — options chain, IV, Greeks, backtesting (roadmap)
- Status page: `trading/public/`

See [`trading/README.md`](trading/README.md) and [`trading/PHASE8_ROADMAP.md`](trading/PHASE8_ROADMAP.md).
