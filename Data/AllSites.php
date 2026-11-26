<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Data;

/**
 * Rules of the overview of all websites that keep it bounded on large instances: one period only (no date range, which
 * Matomo would compute for every website), at most a fixed number of websites (those with the most visits), read in
 * small groups, kept for a short time. Pure.
 */
final class AllSites
{
    public const RANGE = 'range';
    public const SEVERAL = 'several';
    /** websites read together at most; the report tables of a group are released before the next group */
    public const GROUP_SIZE = 10;
    /** campaign names (rows of Matomo's campaign report) read together at most: a very large website is read alone */
    public const NAME_BUDGET = 2000;
    /** up to this many websites the table loads with the page, above it only on a click */
    public const LOAD_WITH_PAGE_UP_TO = 10;
    /** how long a computed table is kept (seconds) */
    public const CACHE_SECONDS = 900;

    /** null for one day, week, month or year; RANGE for a date range; SEVERAL for several periods */
    public static function periodProblem(string $period, string $date): ?string
    {
        if ($period === 'range') {
            return self::RANGE;
        }
        if (PeriodGroups::isSeveral($period, $date)) {
            return self::SEVERAL;
        }
        return null;
    }

    /**
     * At most $max websites: all of them up to the limit, else those with the most visits (a tie and websites without
     * visits by website ID).
     *
     * @param list<int> $idSites
     * @param array<int, int> $visits by website ID
     * @return list<int>
     */
    public static function pick(array $idSites, array $visits, int $max): array
    {
        $idSites = array_values(array_map('intval', $idSites));
        if (count($idSites) <= $max) {
            return $idSites;
        }
        usort($idSites, static function (int $a, int $b) use ($visits): int {
            return [(int) ($visits[$b] ?? 0), $a] <=> [(int) ($visits[$a] ?? 0), $b];
        });
        return array_slice($idSites, 0, $max);
    }

    /**
     * @param list<int> $idSites
     * @return list<list<int>>
     */
    public static function groups(array $idSites): array
    {
        return array_chunk(array_values($idSites), self::GROUP_SIZE);
    }

    /**
     * Groups in the given order with at most $budget campaign names and $max websites each; a website above the budget
     * forms a group of its own.
     *
     * @param list<int> $idSites
     * @param array<int, int> $names campaign names by website (missing: none)
     * @return list<list<int>>
     */
    public static function batches(array $idSites, array $names, int $budget = self::NAME_BUDGET, int $max = self::GROUP_SIZE): array
    {
        $batches = [];
        $batch = [];
        $sum = 0;
        foreach ($idSites as $idSite) {
            $count = (int) ($names[$idSite] ?? 0);
            if ($batch !== [] && ($sum + $count > $budget || count($batch) >= $max)) {
                $batches[] = $batch;
                $batch = [];
                $sum = 0;
            }
            $batch[] = (int) $idSite;
            $sum += $count;
        }
        if ($batch !== []) {
            $batches[] = $batch;
        }
        return $batches;
    }

    /**
     * @param list<int> $idSites
     * @param string $day today (UTC): a relative date such as "yesterday" means another day after midnight
     * @param string $settings a fingerprint of the settings of these websites
     */
    public static function cacheKey(string $login, array $idSites, string $period, string $date, string $segment, string $day, string $settings): string
    {
        return 'fwcs_allsites_' . md5((string) json_encode([$login, array_values($idSites), $period, $date, $segment, $day, $settings]));
    }

    /**
     * The score rows with the period before: previous_score and score_change (points, one decimal) where both periods
     * have a score. Pure.
     *
     * @param array<int, array<string, int|float>> $rows
     * @param array<int, array<string, int|float>> $previous
     * @return array<int, array<string, int|float>>
     */
    public static function withPrevious(array $rows, array $previous): array
    {
        foreach ($rows as $idSite => $columns) {
            $before = $previous[$idSite]['score'] ?? null;
            if ($before === null) {
                continue;
            }
            $rows[$idSite]['previous_score'] = $before;
            if (isset($columns['score'])) {
                $rows[$idSite]['score_change'] = round((float) $columns['score'] - (float) $before, 1);
            }
        }
        return $rows;
    }
}
