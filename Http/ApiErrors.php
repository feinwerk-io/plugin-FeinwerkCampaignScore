<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Http;

/**
 * Error messages of the API that name the parameter, the value given and the allowed values (house rule docs/ai-mcp.md
 * rule 3: an AI client must be able to correct its call from the message alone). The value given is filtered to
 * letters, digits and , . _ - and at most 40 characters, so nothing a caller sends comes back as markup. Pure.
 */
final class ApiErrors
{
    /** the periods getScoresForAllSites accepts (machine values, not translated) */
    public const SINGLE_PERIODS = 'day, week, month, year';

    /** The value as the message shows it: only letters, digits and , . _ -, at most 40 characters. */
    public static function shown($value): string
    {
        $text = is_scalar($value) ? (string) $value : '';

        return substr((string) preg_replace('/[^A-Za-z0-9,._-]/', '', $text), 0, 40);
    }

    /** "Parameter period: <value> is not possible here. Allowed: <allowed>." */
    public static function periodNotHere($period, string $allowed, callable $translate): string
    {
        return (string) $translate('FeinwerkCampaignScore_ApiErrorPeriodNotHere', [self::shown($period), $allowed]);
    }

    /** the values a yes/no parameter such as dryRun accepts (machine values, not translated) */
    public const FLAG_VALUES = '1, 0, true, false';

    /**
     * A yes/no parameter: true for 1 and true, false for 0, false and an empty value (any case, spaces ignored); null for
     * anything else, which the API refuses (a value like "yes" must not count as "no" and save).
     *
     * @param mixed $value
     */
    public static function flag($value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (!is_scalar($value) && $value !== null) {
            return null;
        }
        $text = strtolower(trim((string) $value));
        if (in_array($text, ['1', 'true'], true)) {
            return true;
        }
        if (in_array($text, ['', '0', 'false'], true)) {
            return false;
        }

        return null;
    }

    /** "Parameter <name>: <value> is not allowed. Allowed: <allowed>." */
    public static function badValue(string $parameter, $value, string $allowed, callable $translate): string
    {
        return (string) $translate('FeinwerkCampaignScore_ApiErrorBadValue', [$parameter, self::shown($value), $allowed]);
    }

    /** "Parameter idSite: <value> is not one website. Pass a single website ID, for example 1." */
    public static function oneSite($idSite, callable $translate): string
    {
        return (string) $translate('FeinwerkCampaignScore_ApiErrorOneSite', [self::shown($idSite)]);
    }

    /**
     * "Parameter date: <value> names several periods. This method needs one period: ...", with the range form only for
     * methods that take period=range ($rangeAllowed false: getScoresForAllSites, whose range error would follow next).
     */
    public static function onePeriod($date, callable $translate, bool $rangeAllowed = true): string
    {
        return (string) $translate(
            $rangeAllowed ? 'FeinwerkCampaignScore_ApiErrorOnePeriod' : 'FeinwerkCampaignScore_ApiErrorOnePeriodNoRange',
            [self::shown($date)]
        );
    }
}
