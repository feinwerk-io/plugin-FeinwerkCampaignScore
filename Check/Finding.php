<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Check;

/**
 * One problem in the campaign data: a group of spelling variants, a known source alias, a medium outside the list, a
 * value that is not clean, the wrong separator, a campaign name outside the pattern, campaign visits without a value,
 * or (as a hint that does not lower the score) two similar values that may be a typo.
 */
final class Finding
{
    public const VARIANTS = 'variants';
    public const ALIAS = 'alias';
    public const FORMAT = 'format';
    public const UNUSUAL_MEDIUM = 'unusual_medium';
    public const SEPARATOR = 'separator';
    public const PATTERN = 'pattern';
    public const INCOMPLETE = 'incomplete';
    public const SIMILAR = 'similar';
    /** a campaign name of digits only, such as an ad platform's campaign ID: says nothing in reports (Peter, 2026-11-26) */
    public const NUMERIC = 'numeric';

    /** the order of the types when two findings have the same visits */
    public const TYPES = [
        self::INCOMPLETE, self::VARIANTS, self::ALIAS, self::UNUSUAL_MEDIUM, self::FORMAT, self::SEPARATOR,
        self::PATTERN, self::NUMERIC, self::SIMILAR,
    ];

    /** types whose values count as not clean in the score (a typo hint is only a hint) */
    public const SCORED = [
        self::VARIANTS, self::ALIAS, self::UNUSUAL_MEDIUM, self::FORMAT, self::SEPARATOR, self::PATTERN, self::NUMERIC,
    ];

    /**
     * @param array<string, int> $values the values concerned with their visits, most visited first (empty for INCOMPLETE)
     * @param list<array{name: ?string, source: ?string, medium: ?string, visits: int}> $contexts where the values
     *        to fix occur: campaign name, source and medium, most visited first
     * @param bool $isNew not found in the previous period
     * @param float $scoreGain score points (0 to 100) the finding accounts for: its share of the unclean visits (a visit
     *        with two unclean fields counts half for each) over the checked visits; 0 for findings that do not lower
     *        the score. The points of all findings add up to the points the score misses
     */
    public function __construct(
        public readonly string $type,
        public readonly string $field,
        public readonly array $values,
        public readonly int $visits,
        public readonly ?string $suggestion,
        public readonly array $contexts = [],
        public readonly bool $isNew = false,
        public readonly float $scoreGain = 0.0
    ) {
    }

    /**
     * The values someone has to change: all values, except the suggested spelling of a variant group and the most
     * visited value of a typo pair.
     *
     * @return list<string>
     */
    public function valuesToFix(): array
    {
        $values = array_map('strval', array_keys($this->values));
        if ($this->type === self::VARIANTS) {
            return array_values(array_filter($values, function (string $value): bool {
                return $value !== $this->suggestion;
            }));
        }
        if ($this->type === self::SIMILAR) {
            return array_slice($values, 1);
        }
        return $values;
    }

    /**
     * Identifies the same problem in another period: a variant group by its suggested spelling (its other spellings may
     * change), a missing value by its field, every other finding by the values to fix.
     */
    public function key(): string
    {
        if ($this->type === self::INCOMPLETE) {
            $anchor = '';
        } elseif ($this->type === self::VARIANTS) {
            $anchor = ValueRules::normalize((string) ($this->suggestion ?? array_key_first($this->values)));
        } else {
            $anchor = implode(',', array_map('mb_strtolower', $this->valuesToFix()));
        }
        return $this->type . '|' . $this->field . '|' . $anchor;
    }

    /**
     * @param list<array{name: ?string, source: ?string, medium: ?string, visits: int}> $contexts
     */
    public function with(array $contexts, bool $isNew, float $scoreGain = 0.0): self
    {
        return new self($this->type, $this->field, $this->values, $this->visits, $this->suggestion, $contexts, $isNew, $scoreGain);
    }

    /**
     * @param list<Finding> $findings
     * @return list<Finding> most visits first; then by type and first value, so the order never depends on the input
     */
    public static function sort(array $findings): array
    {
        usort($findings, static function (Finding $a, Finding $b): int {
            return [$b->visits, array_search($a->type, self::TYPES, true), (string) array_key_first($a->values)]
                <=> [$a->visits, array_search($b->type, self::TYPES, true), (string) array_key_first($b->values)];
        });
        return $findings;
    }
}
