<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Check;

/**
 * Turns the campaign values of a website and period into findings and the score.
 *
 * Score = visits whose scored fields are all clean / checked campaign visits. Scored fields: the campaign name, plus
 * source and medium when they are known. Keywords, content, group and placement are checked but not scored. A value
 * is clean when it is accepted, or follows every rule of the convention (FieldChecker::isCleanValue) and is not a
 * spelling to replace in a group of variants. Visits in "Others" rows and visits without any known value cannot be
 * checked and are left out of the basis.
 */
final class Analyzer
{
    private const SCORED_FIELDS = ['name', 'source', 'medium'];
    private const CONTEXTS = 3;

    /**
     * @param Result|null $previous the result of the previous period: marks new findings and keeps its score
     */
    public static function analyze(CampaignData $data, Convention $convention, ?Result $previous = null): Result
    {
        $fields = $data->withSourceMedium
            ? ['name', 'keyword', 'source', 'medium', 'content', 'group', 'placement']
            : ['name', 'keyword'];
        $findings = [];
        foreach ($fields as $field) {
            if (isset($data->fields[$field])) {
                $findings = array_merge($findings, FieldChecker::check($field, $data->fields[$field], $convention));
            }
        }

        if ($data->withSourceMedium) {
            foreach (self::SCORED_FIELDS as $field) {
                $missing = $data->campaignVisits - $data->unknownVisits
                    - (int) array_sum($data->fields[$field] ?? []) - ($data->fieldOthers[$field] ?? 0);
                if ($missing > 0) {
                    $findings[] = new Finding(Finding::INCOMPLETE, $field, [], $missing, null);
                }
            }
        }

        $unchecked = $data->uncheckedVisits + $data->unknownVisits;
        $checked = max(0, $data->campaignVisits - $unchecked);
        $replace = self::valuesToReplace($findings);
        $clean = min($checked, self::cleanVisits($data, $convention, $replace));
        $uncleanShares = self::uncleanShares($data, $convention, $replace);

        // a previous period without campaign visits is no comparison (else every finding would be "new")
        if ($previous !== null && $previous->campaignVisits <= 0) {
            $previous = null;
        }
        $previousKeys = [];
        foreach ($previous !== null ? $previous->findings : [] as $finding) {
            $previousKeys[$finding->key()] = true;
        }
        $decorated = [];
        $currentKeys = [];
        foreach ($findings as $finding) {
            $currentKeys[$finding->key()] = true;
            $decorated[] = $finding->with(
                $data->withSourceMedium ? self::contexts($finding, $data->units) : [],
                $previous !== null && !isset($previousKeys[$finding->key()]),
                self::scoreGain($finding, $uncleanShares, $checked, $data->withSourceMedium)
            );
        }

        return new Result(
            $checked > 0 ? $clean / $checked : null,
            $clean,
            $checked,
            $data->campaignVisits,
            $unchecked,
            $data->withSourceMedium,
            Finding::sort($decorated),
            $previous !== null ? $previous->score : null,
            self::fieldShares($data, $convention, $replace),
            $previous !== null && $checked > 0 ? self::fixedFindings($previous, $currentKeys, $data) : null
        );
    }

    /**
     * The unclean visits shared out to the values that make them unclean: field => value ('' for no value) => visits. A
     * visit with one unclean field counts fully for its value, a visit with two counts half for each (found in the sandbox
     * check of 2026-11-26: counting only the visits a fix alone makes clean showed 0 points for "e-mail", whose visits
     * also had a wrong name), so the points of all findings add up to the points the score misses.
     *
     * @param array<string, array<string, true>> $replace
     * @return array<string, array<string, float>>
     */
    private static function uncleanShares(CampaignData $data, Convention $convention, array $replace): array
    {
        $scored = $data->withSourceMedium ? self::SCORED_FIELDS : ['name'];
        $shares = [];
        foreach ($data->units as $unit) {
            $unclean = [];
            foreach ($scored as $field) {
                if (!self::isClean($field, $unit[$field] ?? null, $convention, $replace)) {
                    $unclean[] = $field;
                }
            }
            foreach ($unclean as $field) {
                $key = (string) ($unit[$field] ?? '');
                $shares[$field][$key] = ($shares[$field][$key] ?? 0.0) + (int) $unit['visits'] / count($unclean);
            }
        }
        return $shares;
    }

    /**
     * Score points (0 to 100, one decimal) the finding accounts for: its share of the unclean visits (uncleanShares).
     * Findings that do not lower the score (typo hints, keyword, content, group, placement) account for none.
     *
     * @param array<string, array<string, float>> $uncleanShares
     */
    private static function scoreGain(Finding $finding, array $uncleanShares, int $checked, bool $withSourceMedium): float
    {
        $scored = $withSourceMedium ? self::SCORED_FIELDS : ['name'];
        if ($checked <= 0 || !in_array($finding->field, $scored, true)
            || !in_array($finding->type, array_merge(Finding::SCORED, [Finding::INCOMPLETE]), true)
        ) {
            return 0.0;
        }
        $visits = 0.0;
        $values = $finding->type === Finding::INCOMPLETE ? [''] : $finding->valuesToFix();
        foreach ($values as $value) {
            $visits += $uncleanShares[$finding->field][(string) $value] ?? 0.0;
        }
        return round(min(100.0, $visits / $checked * 100), 1);
    }

    /**
     * The share of each scored field's visits with a clean value: the values of the field's report (its "Others" rows
     * are not checkable), plus its missing values as not clean when source and medium are known.
     *
     * @param array<string, array<string, true>> $replace
     * @return array<string, float|null>
     */
    private static function fieldShares(CampaignData $data, Convention $convention, array $replace): array
    {
        $shares = [];
        foreach ($data->withSourceMedium ? self::SCORED_FIELDS : ['name'] as $field) {
            $clean = 0;
            $all = 0;
            foreach ($data->fields[$field] ?? [] as $value => $visits) {
                $all += (int) $visits;
                if (self::isClean($field, (string) $value, $convention, $replace)) {
                    $clean += (int) $visits;
                }
            }
            if ($data->withSourceMedium) {
                $all += max(0, $data->campaignVisits - $data->unknownVisits - (int) array_sum($data->fields[$field] ?? [])
                    - ($data->fieldOthers[$field] ?? 0));
            }
            $shares[$field] = $all > 0 ? (float) ($clean / $all) : null;
        }
        return $shares;
    }

    /**
     * Findings of the previous period that are fixed now: gone from this period, and the clean spelling (the
     * suggestion) has visits now, or, for a missing value, the field is complete now. A finding that is only gone (its
     * campaign did not run) does not count.
     *
     * @param array<string, true> $currentKeys
     */
    private static function fixedFindings(Result $previous, array $currentKeys, CampaignData $data): int
    {
        $fixed = 0;
        foreach ($previous->findings as $finding) {
            if (isset($currentKeys[$finding->key()])) {
                continue;
            }
            if ($finding->type === Finding::INCOMPLETE) {
                $fixed += $data->withSourceMedium ? 1 : 0;
                continue;
            }
            $suggestion = (string) $finding->suggestion;
            if ($suggestion !== '' && isset($data->fields[$finding->field][$suggestion])) {
                $fixed++;
            }
        }
        return $fixed;
    }

    /**
     * @param array<string, array<string, true>> $replace
     */
    private static function isClean(string $field, ?string $value, Convention $convention, array $replace): bool
    {
        return $value !== null
            && ($convention->isAccepted($value)
                || (!isset($replace[$field][$value]) && FieldChecker::isCleanValue($field, $value, $convention)));
    }

    /**
     * @param list<Finding> $findings
     * @return array<string, array<string, true>> field => the spellings to replace in groups of variants
     */
    private static function valuesToReplace(array $findings): array
    {
        $replace = [];
        foreach ($findings as $finding) {
            if ($finding->type === Finding::VARIANTS) {
                foreach ($finding->valuesToFix() as $value) {
                    $replace[$finding->field][$value] = true;
                }
            }
        }
        return $replace;
    }

    /**
     * @param array<string, array<string, true>> $replace
     */
    private static function cleanVisits(CampaignData $data, Convention $convention, array $replace): int
    {
        $scored = $data->withSourceMedium ? self::SCORED_FIELDS : ['name'];
        $clean = 0;
        foreach ($data->units as $unit) {
            foreach ($scored as $field) {
                if (!self::isClean($field, $unit[$field] ?? null, $convention, $replace)) {
                    continue 2;
                }
            }
            $clean += (int) $unit['visits'];
        }
        return $clean;
    }

    /**
     * Where the values to fix occur: the combinations of campaign name, source and medium with the most visits.
     *
     * @param list<array{name: ?string, source: ?string, medium: ?string, visits: int}> $units
     * @return list<array{name: ?string, source: ?string, medium: ?string, visits: int}>
     */
    private static function contexts(Finding $finding, array $units): array
    {
        if (!in_array($finding->field, self::SCORED_FIELDS, true)) {
            return [];
        }
        $fix = $finding->type === Finding::INCOMPLETE ? null : array_fill_keys($finding->valuesToFix(), true);
        $matches = [];
        foreach ($units as $unit) {
            $value = $unit[$finding->field] ?? null;
            if ($fix === null ? $value === null : ($value !== null && isset($fix[$value]))) {
                $matches[] = $unit;
            }
        }
        usort($matches, static function (array $a, array $b): int {
            return [$b['visits'], (string) $a['name'], (string) $a['source']] <=> [$a['visits'], (string) $b['name'], (string) $b['source']];
        });
        return array_slice($matches, 0, self::CONTEXTS);
    }
}
