<?php

declare(strict_types=1);

namespace Trading;

/**
 * US cash-equity calendar helpers.
 * Sync is scheduled for 4:00 PM America/New_York, Mon–Fri, excluding holidays.
 */
final class MarketCalendar
{
    public const TIMEZONE = 'America/New_York';

    /**
     * Common NYSE/Nasdaq full-day closures (extend yearly as needed).
     *
     * @var list<string> Y-m-d
     */
    private const US_HOLIDAYS = [
        // 2025
        '2025-01-01', '2025-01-20', '2025-02-17', '2025-04-18',
        '2025-05-26', '2025-06-19', '2025-07-04', '2025-09-01',
        '2025-11-27', '2025-12-25',
        // 2026
        '2026-01-01', '2026-01-19', '2026-02-16', '2026-04-03',
        '2026-05-25', '2026-06-19', '2026-07-03', '2026-09-07',
        '2026-11-26', '2026-12-25',
        // 2027
        '2027-01-01', '2027-01-18', '2027-02-15', '2027-03-26',
        '2027-05-31', '2027-06-18', '2027-07-05', '2027-09-06',
        '2027-11-25', '2027-12-24',
    ];

    public static function nowEt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE));
    }

    public static function todayEt(): string
    {
        return self::nowEt()->format('Y-m-d');
    }

    public static function isWeekend(\DateTimeInterface $dt): bool
    {
        $dow = (int) $dt->format('N'); // 6=Sat, 7=Sun
        return $dow >= 6;
    }

    public static function isHoliday(\DateTimeInterface $dt): bool
    {
        return in_array($dt->format('Y-m-d'), self::US_HOLIDAYS, true);
    }

    /** Regular session day: Mon–Fri and not a full holiday. */
    public static function isTradingDay(?\DateTimeInterface $dt = null): bool
    {
        $dt ??= self::nowEt();
        if (self::isWeekend($dt)) {
            return false;
        }
        if (self::isHoliday($dt)) {
            return false;
        }

        return true;
    }

    /**
     * Why sync should be skipped, or null if it should run.
     */
    public static function skipReason(?\DateTimeInterface $dt = null): ?string
    {
        $dt ??= self::nowEt();
        if (self::isWeekend($dt)) {
            return 'weekend (' . $dt->format('l') . ')';
        }
        if (self::isHoliday($dt)) {
            return 'US market holiday (' . $dt->format('Y-m-d') . ')';
        }

        return null;
    }
}
