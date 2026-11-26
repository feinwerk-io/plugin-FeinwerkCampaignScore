<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Ui;

use Piwik\Plugins\FeinwerkCampaignScore\Check\Finding;

/**
 * The readable form of a finding in the report and the widget.
 */
final class FindingTexts
{
    /** finding type => translation key */
    public const TYPES = [
        Finding::INCOMPLETE => 'FeinwerkCampaignScore_TypeIncomplete',
        Finding::VARIANTS => 'FeinwerkCampaignScore_TypeVariants',
        Finding::ALIAS => 'FeinwerkCampaignScore_TypeAlias',
        Finding::UNUSUAL_MEDIUM => 'FeinwerkCampaignScore_TypeUnusualMedium',
        Finding::FORMAT => 'FeinwerkCampaignScore_TypeFormat',
        Finding::SEPARATOR => 'FeinwerkCampaignScore_TypeSeparator',
        Finding::PATTERN => 'FeinwerkCampaignScore_TypePattern',
        Finding::SIMILAR => 'FeinwerkCampaignScore_TypeSimilar',
    ];

    /** campaign field => translation key */
    public const FIELDS = [
        'name' => 'FeinwerkCampaignScore_FieldName',
        'keyword' => 'FeinwerkCampaignScore_FieldKeyword',
        'source' => 'FeinwerkCampaignScore_FieldSource',
        'medium' => 'FeinwerkCampaignScore_FieldMedium',
        'content' => 'FeinwerkCampaignScore_FieldContent',
        'group' => 'FeinwerkCampaignScore_FieldGroup',
        'placement' => 'FeinwerkCampaignScore_FieldPlacement',
    ];

    /**
     * The value column: spelling variants with their visits, "(not set)" for a missing value, else the value.
     *
     * @param array<string|int, int> $values value => visits
     * @param callable(string): string $translate
     * @param callable(int|float): string $formatNumber
     */
    public static function value(string $type, string $label, array $values, callable $translate, callable $formatNumber): string
    {
        if ($type === Finding::INCOMPLETE) {
            return $translate('FeinwerkCampaignScore_EmptyValue');
        }
        if (in_array($type, [Finding::VARIANTS, Finding::SIMILAR], true) && $values !== []) {
            $parts = [];
            foreach ($values as $value => $visits) {
                $parts[] = $value . ' (' . $formatNumber($visits) . ')';
            }
            return implode(', ', $parts);
        }
        return $label;
    }

    /** @param callable(string): string $translate */
    public static function type(string $type, callable $translate): string
    {
        return isset(self::TYPES[$type]) ? $translate(self::TYPES[$type]) : $type;
    }

    /** @param callable(string): string $translate */
    public static function field(string $field, callable $translate): string
    {
        return isset(self::FIELDS[$field]) ? $translate(self::FIELDS[$field]) : $field;
    }

    /**
     * Where the values occur, as plain text (Matomo's HTML encoding undone; escape it for HTML):
     * "winter-sale (facebook, Facebook Ads), spring-sale (google, cpc)".
     *
     * @param list<array{name: ?string, source: ?string, medium: ?string, visits: int}> $contexts
     * @param callable(string): string $translate
     */
    public static function contexts(array $contexts, callable $translate): string
    {
        $empty = $translate('FeinwerkCampaignScore_EmptyValue');
        $text = static function ($value) use ($empty): string {
            return $value === null || $value === '' ? $empty : html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        };
        $parts = [];
        foreach ($contexts as $context) {
            $parts[] = $text($context['name'] ?? null) . ' (' . $text($context['source'] ?? null) . ', ' . $text($context['medium'] ?? null) . ')';
        }
        return implode(', ', $parts);
    }
    /**
     * What to do with a finding that has no clean suggestion (UI review 2026-11-25 D7), as plain text: a missing value is
     * to be added, an unusual medium is not in the list, a name outside the pattern says so; else a general "change it in
     * the links". $field is kept for later texts per field.
     *
     * @param callable(string, array=): string $translate like Piwik::translate(string $key, array $args = [])
     */
    public static function hint(string $type, string $field, callable $translate): string
    {
        if ($type === Finding::INCOMPLETE) {
            return $translate('FeinwerkCampaignScore_HintIncomplete');
        }
        if ($type === Finding::UNUSUAL_MEDIUM) {
            return $translate('FeinwerkCampaignScore_HintUnusualMedium');
        }
        if ($type === Finding::PATTERN) {
            return $translate('FeinwerkCampaignScore_HintPattern');
        }
        if ($type === Finding::SIMILAR) {
            return $translate('FeinwerkCampaignScore_HintSimilar');
        }
        return $translate('FeinwerkCampaignScore_HintChangeInLinks');
    }

    /**
     * The spelling variants as chips with their visits, the suggested spelling marked (UI review proposal j). HTML,
     * every value escaped once (Matomo stores tracked values HTML-encoded: decoded first). aria-hidden: the label next
     * to it says the same for screen readers.
     *
     * @param array<string|int, int> $values value => visits
     * @param callable(int|float): string $formatNumber
     */
    public static function chips(array $values, string $suggestion, callable $formatNumber): string
    {
        $html = '';
        foreach ($values as $value => $visits) {
            $text = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $clean = $suggestion !== '' && (string) $value === $suggestion;
            $html .= '<span class="fwcs-chip' . ($clean ? ' fwcs-chip--clean' : '') . '">'
                . ($clean ? '<span class="fwcs-chip__mark">&#10003;</span>' : '')
                . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
                . '<span class="fwcs-chip__count">' . htmlspecialchars($formatNumber($visits), ENT_QUOTES, 'UTF-8') . '</span></span>';
        }
        return $html === '' ? '' : '<span class="fwcs-chips" aria-hidden="true">' . $html . '</span>';
    }

    /**
     * The filter above the findings table: "All" and one chip per type present, each with its count; the active one
     * pressed (javascripts/findings.js reloads the table with the parameter findingType). '' with fewer than two types.
     *
     * @param array<string, int> $counts finding type => findings
     * @param list<string> $active the types filtered for, [] for all
     * @param callable(string, array=): string $translate
     * @param callable(int|float): string $formatNumber
     */
    public static function filter(array $counts, array $active, callable $translate, callable $formatNumber): string
    {
        $present = [];
        foreach (Finding::TYPES as $type) {
            if (($counts[$type] ?? 0) > 0) {
                $present[$type] = (int) $counts[$type];
            }
        }
        if (count($present) < 2) {
            return '';
        }
        $chip = static function (string $type, string $label, int $count, bool $pressed) use ($formatNumber): string {
            return '<button type="button" class="fwcs-filter__chip' . ($pressed ? ' is-active' : '') . '" data-fwcs-type="'
                . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '" aria-pressed="' . ($pressed ? 'true' : 'false') . '">'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                . ' <span class="fwcs-filter__count">' . htmlspecialchars($formatNumber($count), ENT_QUOTES, 'UTF-8') . '</span></button>';
        };
        $html = $chip('', $translate('FeinwerkCampaignScore_FilterAll'), (int) array_sum($present), $active === []);
        foreach ($present as $type => $count) {
            $html .= $chip($type, self::type($type, $translate), $count, $active === [$type]);
        }
        return '<div class="fwcs-filter" role="group" aria-label="'
            . htmlspecialchars($translate('FeinwerkCampaignScore_FilterLabel'), ENT_QUOTES, 'UTF-8') . '">' . $html . '</div>';
    }
}
