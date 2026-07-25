<?php

declare(strict_types=1);

namespace Trading;

use RuntimeException;

/**
 * Unofficial Yahoo Finance chart API client for daily OHLC bars.
 */
final class YahooFinanceClient
{
    private const CHART_URL = 'https://query1.finance.yahoo.com/v8/finance/chart/%s';

    public function __construct(
        private readonly string $userAgent = 'Mozilla/5.0 (compatible; TradingDashboard/1.0)'
    ) {
    }

    /**
     * @return list<array{
     *   date: string,
     *   open: ?float,
     *   high: ?float,
     *   low: ?float,
     *   close: float,
     *   volume: ?int
     * }>
     */
    public function fetchDailyOhlc(string $yahooSymbol, string $range = '1y'): array
    {
        $url = sprintf(self::CHART_URL, rawurlencode($yahooSymbol));
        $url .= '?' . http_build_query([
            'interval' => '1d',
            'range' => $range,
            'includePrePost' => 'false',
            'events' => 'div,splits',
        ]);

        $payload = $this->getJson($url);
        $result = $payload['chart']['result'][0] ?? null;

        if ($result === null) {
            $error = $payload['chart']['error']['description'] ?? 'Unknown Yahoo Finance error';
            throw new RuntimeException("No chart data for {$yahooSymbol}: {$error}");
        }

        $timestamps = $result['timestamp'] ?? [];
        $quote = $result['indicators']['quote'][0] ?? [];
        $meta = $result['meta'] ?? [];
        $gmtOffset = (int) ($meta['gmtoffset'] ?? 0);
        $livePrice = isset($meta['regularMarketPrice']) ? (float) $meta['regularMarketPrice'] : null;

        if ($timestamps === [] || $quote === []) {
            throw new RuntimeException("Empty OHLC series for {$yahooSymbol}");
        }

        $opens = $quote['open'] ?? [];
        $highs = $quote['high'] ?? [];
        $lows = $quote['low'] ?? [];
        $closes = $quote['close'] ?? [];
        $volumes = $quote['volume'] ?? [];

        $rows = [];
        $lastIndex = count($timestamps) - 1;

        foreach ($timestamps as $i => $ts) {
            $close = $closes[$i] ?? null;

            // At/after the 4:00 PM close, Yahoo often leaves today's daily close null
            // for a while. Use the live regularMarketPrice as the EOD close proxy.
            if ($close === null && $i === $lastIndex && $livePrice !== null) {
                $close = $livePrice;
            }

            if ($close === null) {
                continue;
            }

            $rows[] = [
                'date' => gmdate('Y-m-d', (int) $ts + $gmtOffset),
                'open' => isset($opens[$i]) && $opens[$i] !== null ? (float) $opens[$i] : null,
                'high' => isset($highs[$i]) && $highs[$i] !== null ? (float) $highs[$i] : null,
                'low' => isset($lows[$i]) && $lows[$i] !== null ? (float) $lows[$i] : null,
                'close' => round((float) $close, 2),
                'volume' => isset($volumes[$i]) && $volumes[$i] !== null ? (int) $volumes[$i] : null,
            ];
        }

        return $rows;
    }

    private function getJson(string $url): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: ' . $this->userAgent,
            ],
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Yahoo Finance request failed: ' . $error);
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Yahoo Finance HTTP {$status}: " . substr((string) $body, 0, 200));
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON from Yahoo Finance');
        }

        return $decoded;
    }
}
