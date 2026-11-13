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
}
