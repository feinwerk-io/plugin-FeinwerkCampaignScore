<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Check;

/**
 * Checks the values of one campaign field against a convention. Accepted values are left out. Each other value lands
 * in at most one finding, in this order: spelling variants (with source aliases for the source), a known source alias,
 * a medium outside the list, a value that is not clean, the wrong separator, a campaign name outside the pattern.
 * Typo hints (similar values) come on top and do not lower the score.
 */
final class FieldChecker
{
    /** which rules apply to which field; keyword and content hold free text and get the variant check only */
    private const PROFILES = [
        'name' => ['format' => true, 'separator' => true, 'pattern' => true, 'typos' => true, 'numeric' => true],
        'source' => ['format' => true, 'separator' => true, 'aliases' => true, 'typos' => true],
        'medium' => ['format' => true, 'separator' => true, 'list' => true, 'typos' => true],
        'keyword' => [],
        'content' => [],
        'group' => ['format' => true, 'separator' => true],
        'placement' => ['format' => true, 'separator' => true],
    ];

    /** typo hints: only for normalized values of at least this length, and at most this many per field */
    private const TYPO_MIN_LENGTH = 8;
    private const TYPO_MAX_FINDINGS = 50;

    /**
     * @param array<string|int, int> $values value => visits (PHP turns numeric keys into integers)
     * @return list<Finding>
     */
    public static function check(string $field, array $values, ?Convention $convention = null): array
    {
        $convention = $convention ?? Convention::defaults();
        $profile = self::PROFILES[$field] ?? [];
        $aliases = !empty($profile['aliases']) && $convention->sourceAliases;

        $groups = [];
        foreach ($values as $value => $visits) {
            $value = (string) $value;
            if ($value === '' || $convention->isAccepted($value)) {
                continue;
            }
            $canonical = $aliases ? SourceAliases::canonical($value) : null;
            $groups[ValueRules::normalize($canonical ?? $value)][$value] = (int) $visits;
        }

        $findings = [];
        $flagged = [];
        foreach ($groups as $group) {
            $group = self::byVisits($group);
            $first = (string) array_key_first($group);
            if (count($group) > 1) {
                $canonical = $aliases ? SourceAliases::canonical($first) : null;
                $suggestion = $canonical ?? self::suggestion($field, $group, $convention);
                $findings[] = new Finding(Finding::VARIANTS, $field, $group, (int) array_sum($group), $suggestion);
                continue;
            }
            $finding = self::singleValueFinding($field, $first, $group[$first], $convention);
            if ($finding !== null) {
                $findings[] = $finding;
                $flagged[$first] = true;
            }
        }

        if (!empty($profile['typos']) && $convention->typoHints) {
            $findings = array_merge($findings, self::typoHints($field, $groups, $convention, $flagged));
        }

        return Finding::sort($findings);
    }

    /**
     * Whether one value follows every single-value rule of its field (variants need the other values, see Analyzer).
     */
    public static function isCleanValue(string $field, string $value, Convention $convention): bool
    {
        if ($convention->isAccepted($value)) {
            return true;
        }
        return self::singleValueFinding($field, $value, 0, $convention) === null;
    }

    private static function singleValueFinding(string $field, string $value, int $visits, Convention $convention): ?Finding
    {
        $profile = self::PROFILES[$field] ?? [];
        $group = [$value => $visits];

        if (!empty($profile['aliases']) && $convention->sourceAliases) {
            $canonical = SourceAliases::canonical($value);
            if ($canonical !== null && $canonical !== $value) {
                return new Finding(Finding::ALIAS, $field, $group, $visits, $canonical);
            }
        }
        if (empty($profile['format'])) {
            return null;
        }

        // digits only: no spelling makes it a readable name, so this comes before the format (Peter, 2026-11-26)
        if (!empty($profile['numeric']) && $convention->numericNames && ValueRules::isNumericOnly($value)) {
            return new Finding(Finding::NUMERIC, $field, $group, $visits, null);
        }

        $cleaned = ValueRules::cleaned($value, $convention->separator);
        if (!empty($profile['list']) && !$convention->mediums->contains($cleaned) && !$convention->mediums->contains($value)) {
            return new Finding(Finding::UNUSUAL_MEDIUM, $field, $group, $visits, null);
        }
        if (!ValueRules::isClean($value)) {
            return new Finding(Finding::FORMAT, $field, $group, $visits, $cleaned === '' ? null : $cleaned);
        }
        if (!empty($profile['separator'])) {
            $fixed = $convention->separatorFix($value);
            if ($fixed !== null) {
                return new Finding(Finding::SEPARATOR, $field, $group, $visits, $fixed);
            }
        }
        if (!empty($profile['pattern']) && !$convention->matchesNamePattern($value)) {
            return new Finding(Finding::PATTERN, $field, $group, $visits, null);
        }
        return null;
    }

    /**
     * Pairs of values that are close to each other (Levenshtein distance 1, or 2 from 12 characters), compared in
     * normalized form; values that differ only in digits ("sale-2025", "sale-2026") are not paired. Each value pairs
     * with the most visited match only.
     *
     * @param array<string, array<string, int>> $groups normalized key => value => visits
     * @param array<string, true> $flagged values that already have a finding: never the rare half of a pair
     * @return list<Finding>
     */
    private static function typoHints(string $field, array $groups, Convention $convention, array $flagged): array
    {
        $candidates = [];
        foreach ($groups as $key => $group) {
            $key = (string) $key;
            if (mb_strlen($key) >= self::TYPO_MIN_LENGTH) {
                $group = self::byVisits($group);
                $candidates[] = ['key' => $key, 'value' => (string) array_key_first($group), 'visits' => (int) array_sum($group)];
            }
        }
        usort($candidates, static function (array $a, array $b): int {
            return [$b['visits'], $a['value']] <=> [$a['visits'], $b['value']];
        });

        $findings = [];
        $paired = [];
        foreach ($candidates as $i => $common) {
            foreach ($candidates as $j => $rare) {
                if ($j <= $i || isset($paired[$j]) || isset($flagged[$rare['value']]) || count($findings) >= self::TYPO_MAX_FINDINGS) {
                    continue;
                }
                if (!self::isLikelyTypo($common['key'], $rare['key'])) {
                    continue;
                }
                $paired[$j] = true;
                $suggestion = FieldChecker::isCleanValue($field, $common['value'], $convention)
                    ? $common['value']
                    : ValueRules::cleaned($common['value'], $convention->separator);
                $findings[] = new Finding(
                    Finding::SIMILAR,
                    $field,
                    [$common['value'] => $common['visits'], $rare['value'] => $rare['visits']],
                    $rare['visits'],
                    $suggestion === '' ? null : $suggestion
                );
            }
        }
        return $findings;
    }

    private static function isLikelyTypo(string $a, string $b): bool
    {
        $lengthA = mb_strlen($a, 'UTF-8');
        $lengthB = mb_strlen($b, 'UTF-8');
        $allowed = min($lengthA, $lengthB) >= 12 ? 2 : 1;
        if (abs($lengthA - $lengthB) > $allowed || $lengthA > 255 || $lengthB > 255) {
            return false;
        }
        if (preg_replace('/[0-9]+/', '', $a) === preg_replace('/[0-9]+/', '', $b)) {
            return false;
        }
        return self::distance($a, $b) <= $allowed;
    }

    /** Levenshtein distance in characters (PHP's levenshtein() counts bytes, so an umlaut would count twice). */
    public static function distance(string $a, string $b): int
    {
        if (!preg_match('/[^\x00-\x7F]/', $a . $b)) {
            return levenshtein($a, $b);
        }
        $x = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $y = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $previous = range(0, count($y));
        foreach ($x as $i => $charA) {
            $current = [$i + 1];
            foreach ($y as $j => $charB) {
                $current[$j + 1] = min($previous[$j + 1] + 1, $current[$j] + 1, $previous[$j] + ($charA === $charB ? 0 : 1));
            }
            $previous = $current;
        }
        return (int) $previous[count($y)];
    }

    /**
     * @param array<string|int, int> $group
     * @return array<string, int>
     */
    private static function byVisits(array $group): array
    {
        uksort($group, static function ($a, $b) use ($group): int {
            return [$group[$b], (string) $a] <=> [$group[$a], (string) $b];
        });
        $sorted = [];
        foreach ($group as $value => $visits) {
            $sorted[(string) $value] = $visits;
        }
        return $sorted;
    }

    /**
     * The most visited value of the group that follows all single-value rules, else the clean form of the most
     * visited value.
     *
     * @param array<string, int> $group most visited first
     */
    private static function suggestion(string $field, array $group, Convention $convention): ?string
    {
        foreach ($group as $value => $visits) {
            if (ValueRules::isClean((string) $value) && self::isCleanValue($field, (string) $value, $convention)) {
                return (string) $value;
            }
        }
        $cleaned = ValueRules::cleaned((string) array_key_first($group), $convention->separator);
        return $cleaned === '' ? null : $cleaned;
    }
}
