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
        $clean = min($checked, self::cleanVisits($data, $convention, self::valuesToReplace($findings)));

        // a previous period without campaign visits is no comparison (else every finding would be "new")
        if ($previous !== null && $previous->campaignVisits <= 0) {
            $previous = null;
        }
        $previousKeys = [];
        foreach ($previous !== null ? $previous->findings : [] as $finding) {
            $previousKeys[$finding->key()] = true;
        }
        $decorated = [];
        foreach ($findings as $finding) {
            $decorated[] = $finding->with(
                $data->withSourceMedium ? self::contexts($finding, $data->units) : [],
                $previous !== null && !isset($previousKeys[$finding->key()])
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
            $previous !== null ? $previous->score : null
        );
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
                $value = $unit[$field] ?? null;
                if (
                    $value === null
                    || (!$convention->isAccepted($value)
                        && (isset($replace[$field][$value]) || !FieldChecker::isCleanValue($field, $value, $convention)))
                ) {
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
