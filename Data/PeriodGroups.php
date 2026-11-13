<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Data;

/**
 * Several periods (date "A,B", "lastN" or "previousN" with day, week, month or year) split into groups of a few
 * periods, so that the reports of only one group are in memory at a time (load test 2026-11-13). Matomo labels every
 * period the same way in a group as in the whole range. Pure.
 */
final class PeriodGroups
{
    /** periods read together */
    public const SIZE = 7;

    public static function isSeveral(string $period, string $date): bool
    {
        return $period !== 'range' && (strpos($date, ',') !== false || preg_match('/^(last|previous)\d+$/', $date) === 1);
    }

    /**
     * The dates of the groups, each "first,last" by the first day of its first and last period (as Matomo lists the
     * periods of the whole range).
     *
     * @param list<string> $startDates first day of every period, in order (YYYY-MM-DD)
     * @return list<string>
     */
    public static function dates(array $startDates, int $size = self::SIZE): array
    {
        $dates = [];
        foreach (array_chunk(array_values($startDates), max(1, $size)) as $group) {
            $dates[] = $group[0] . ',' . $group[count($group) - 1];
        }
        return $dates;
    }
}
