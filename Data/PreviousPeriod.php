<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Data;

use Piwik\Date;

/**
 * The period right before the selected one: the same day, week, month or year one step earlier, or a date range of
 * the same length ending the day before. None for several periods at once (date=last7 with period=day).
 */
final class PreviousPeriod
{
    private const STEPS = ['day', 'week', 'month', 'year'];

    /**
     * @return array{period: string, date: string}|null
     */
    public static function of(string $period, string $date, string $timezone): ?array
    {
        try {
            if (in_array($period, self::STEPS, true)) {
                if (strpos($date, ',') !== false || preg_match('/^(last|previous)\d+$/', $date)) {
                    return null;
                }
                $previous = Date::factory($date, $timezone)->subPeriod(1, $period);
                return ['period' => $period, 'date' => $previous->toString()];
            }
            if ($period !== 'range') {
                return null;
            }

            [$start, $end] = self::range($date, $timezone);
            $days = (int) round(($end->getTimestamp() - $start->getTimestamp()) / 86400) + 1;
            $previousEnd = $start->subDay(1);
            $previousStart = $previousEnd->subDay($days - 1);
            return ['period' => 'range', 'date' => $previousStart->toString() . ',' . $previousEnd->toString()];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array{0: Date, 1: Date}
     */
    private static function range(string $date, string $timezone): array
    {
        if (preg_match('/^(last|previous)(\d+)$/', $date, $m)) {
            $end = Date::factory($m[1] === 'last' ? 'today' : 'yesterday', $timezone);
            return [$end->subDay((int) $m[2] - 1), $end];
        }
        $parts = explode(',', $date);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('range');
        }
        return [Date::factory(trim($parts[0]), $timezone), Date::factory(trim($parts[1]), $timezone)];
    }
}
