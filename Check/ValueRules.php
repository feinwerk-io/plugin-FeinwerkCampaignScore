<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Check;

/**
 * What a clean campaign value is. A clean value is lowercase and uses only a-z, 0-9, "_", "-" and ".". Two values are
 * spelling variants of each other when they are equal after lowercasing and removing whitespace, "_", "-" and ".".
 */
final class ValueRules
{
    private const TRANSLITERATION = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss'];

    public static function normalize(string $value): string
    {
        return (string) preg_replace('/[\s_.\-]+/u', '', mb_strtolower($value, 'UTF-8'));
    }

    public static function isClean(string $value): bool
    {
        return preg_match('/^[a-z0-9_.\-]+$/D', $value) === 1;
    }

    /**
     * The clean form of a value, as a suggestion: lowercase, German umlauts spelled out, every run of other characters
     * replaced by one "-" (by "_" when the convention uses it; with a separator rule the other one is converted too).
     * Empty when nothing usable is left. Matomo stores tracked values HTML-encoded ("&amp;"), so entities are decoded
     * first.
     */
    public static function cleaned(string $value, string $separator = Convention::SEPARATOR_ANY): string
    {
        $joiner = $separator === Convention::SEPARATOR_UNDERSCORE ? '_' : '-';
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = strtr(mb_strtolower($value, 'UTF-8'), self::TRANSLITERATION);
        $value = (string) preg_replace('/[^a-z0-9_.\-]+/', $joiner, $value);
        if ($separator === Convention::SEPARATOR_HYPHEN) {
            $value = str_replace('_', '-', $value);
        } elseif ($separator === Convention::SEPARATOR_UNDERSCORE) {
            $value = str_replace('-', '_', $value);
        }
        $value = (string) preg_replace('/([-_]){2,}/', '$1', $value);
        return trim($value, '-_');
    }
}
