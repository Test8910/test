<?php

declare(strict_types=1);

namespace Trading;

/**
 * Phase 5 multi-market labels and display helpers.
 */
final class Markets
{
    /** @var array<string, array{label: string, flag: string, currency: string}> */
    public const REGIONS = [
        'US' => ['label' => 'United States', 'flag' => '🇺🇸', 'currency' => 'USD'],
        'IN' => ['label' => 'India', 'flag' => '🇮🇳', 'currency' => 'INR'],
        'UK' => ['label' => 'United Kingdom', 'flag' => '🇬🇧', 'currency' => 'GBP'],
        'ASIA' => ['label' => 'Asia', 'flag' => '🌏', 'currency' => 'JPY'],
    ];

    public static function label(string $market): string
    {
        return self::REGIONS[$market]['label'] ?? $market;
    }

    public static function flag(string $market): string
    {
        return self::REGIONS[$market]['flag'] ?? '';
    }

    public static function formatPrice(string $market, float|string $price): string
    {
        $value = (float) $price;

        return match (strtoupper($market)) {
            'IN' => '₹' . number_format($value, 2),
            'UK' => '£' . number_format($value, 2),
            'ASIA', 'JP' => '¥' . number_format($value, 2),
            default => '$' . number_format($value, 2),
        };
    }

    /**
     * Preferred dashboard section order.
     *
     * @return list<string>
     */
    public static function order(): array
    {
        return ['US', 'IN', 'UK', 'ASIA'];
    }
}
