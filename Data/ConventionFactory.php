<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Data;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Db;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Convention;
use Piwik\Plugins\FeinwerkCampaignScore\Check\MediumList;
use Piwik\Plugins\FeinwerkCampaignScore\MeasurableSettings;
use Piwik\Plugins\FeinwerkCampaignScore\SystemSettings;

/**
 * The convention of a website: the system settings (all websites) with the website's own settings on top. For many
 * websites at once the website settings are read with one query (forSites), not one per website.
 */
final class ConventionFactory
{
    public const PLUGIN = 'FeinwerkCampaignScore';
    public const SEPARATOR_INHERIT = 'inherit';

    /**
     * @param array<string, mixed> $system mediums, separator, namePattern, sourceAliases, typoHints
     * @param array<string, mixed> $site mediums, accepted, separator, namePattern (empty or "inherit": the system's)
     */
    public static function build(array $system, array $site): Convention
    {
        $mediums = trim((string) ($site['mediums'] ?? '')) !== '' ? (string) $site['mediums'] : (string) ($system['mediums'] ?? '');

        $separator = (string) ($site['separator'] ?? self::SEPARATOR_INHERIT);
        if (!self::isSeparator($separator)) {
            $separator = (string) ($system['separator'] ?? Convention::SEPARATOR_ANY);
        }
        if (!self::isSeparator($separator)) {
            $separator = Convention::SEPARATOR_ANY;
        }

        $pattern = trim((string) ($site['namePattern'] ?? ''));
        if ($pattern === '') {
            $pattern = trim((string) ($system['namePattern'] ?? ''));
        }
        if ($pattern === '' || !Convention::isValidPattern($pattern)) {
            $pattern = null;
        }

        return new Convention(
            MediumList::fromText($mediums),
            Convention::acceptedFromText((string) ($site['accepted'] ?? '')),
            $separator,
            $pattern,
            self::flag($system['sourceAliases'] ?? true),
            self::flag($system['typoHints'] ?? true)
        );
    }

    /**
     * Rows of Matomo's site_setting table (idsite, setting_name, setting_value, json_encoded) as idsite => settings.
     *
     * @param list<array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public static function siteSettingsFromRows(array $rows): array
    {
        $sites = [];
        foreach ($rows as $row) {
            $value = $row['setting_value'] ?? '';
            if (!empty($row['json_encoded'])) {
                $value = json_decode((string) $value, true);
            }
            $sites[(int) $row['idsite']][(string) $row['setting_name']] = $value;
        }
        return $sites;
    }

    /** The convention of one website, with Matomo's settings. */
    public static function forSite(int $idSite): Convention
    {
        $site = new MeasurableSettings($idSite);
        return self::build(self::systemValues(), [
            'mediums' => $site->mediums->getValue(),
            'accepted' => $site->accepted->getValue(),
            'separator' => $site->separator->getValue(),
            'namePattern' => $site->namePattern->getValue(),
        ]);
    }

    /**
     * The conventions of several websites: the system settings once, the website settings in one query.
     *
     * @param list<int> $idSites
     * @return array<int, Convention>
     */
    public static function forSites(array $idSites): array
    {
        $idSites = array_values(array_unique(array_map('intval', $idSites)));
        $siteSettings = [];
        if ($idSites !== []) {
            $rows = Db::fetchAll(
                'SELECT idsite, setting_name, setting_value, json_encoded FROM ' . Common::prefixTable('site_setting')
                . ' WHERE plugin_name = ? AND idsite IN (' . implode(',', $idSites) . ')',
                [self::PLUGIN]
            );
            $siteSettings = self::siteSettingsFromRows($rows);
        }

        $system = self::systemValues();
        $conventions = [];
        foreach ($idSites as $idSite) {
            $conventions[$idSite] = self::build($system, $siteSettings[$idSite] ?? []);
        }
        return $conventions;
    }

    /**
     * The rules in force for one website and where each comes from (website setting, setting for all websites, or the
     * plugin's default), for FeinwerkCampaignScore.getRules. Mirrors build(). Pure.
     *
     * @param array<string, mixed> $system the settings for all websites
     * @param array<string, mixed> $site   the website's settings
     * @return array<string, mixed>
     */
    public static function rulesFrom(array $system, array $site): array
    {
        $convention = self::build($system, $site);
        $sourceOf = static function (string $name) use ($system, $site): string {
            if (trim((string) ($site[$name] ?? '')) !== '' && !($name === 'separator' && !self::isSeparator((string) $site[$name]))) {
                return 'website';
            }
            $value = trim(str_replace(["\r\n", "\r"], "\n", (string) ($system[$name] ?? '')));
            // Matomo returns a setting's default when nothing is stored: the default is not a choice for all websites
            if ($value !== '' && $value !== self::systemDefault($name) && !($name === 'separator' && !self::isSeparator($value))) {
                return 'all_websites';
            }

            return 'default';
        };
        $separatorNames = [Convention::SEPARATOR_ANY => 'any', Convention::SEPARATOR_HYPHEN => 'hyphen', Convention::SEPARATOR_UNDERSCORE => 'underscore'];

        return [
            'separator' => ['value' => $separatorNames[$convention->separator] ?? 'any', 'source' => $sourceOf('separator')],
            'name_pattern' => ['value' => $convention->namePattern, 'source' => $convention->namePattern === null ? 'default' : $sourceOf('namePattern')],
            'mediums' => ['value' => $convention->mediums->values(), 'source' => $sourceOf('mediums')],
            'accepted_values' => array_map('strval', array_keys(Convention::acceptedFromText((string) ($site['accepted'] ?? '')))),
            'source_aliases' => self::flag($system['sourceAliases'] ?? true),
            'typo_hints' => self::flag($system['typoHints'] ?? true),
        ];
    }

    /** @return array<string, mixed> rulesFrom() for one website */
    public static function rulesForSite(int $idSite): array
    {
        $site = new MeasurableSettings($idSite);

        return self::rulesFrom(self::systemValues(), [
            'mediums' => $site->mediums->getValue(),
            'accepted' => $site->accepted->getValue(),
            'separator' => $site->separator->getValue(),
            'namePattern' => $site->namePattern->getValue(),
        ]);
    }

    /** The default of a setting for all websites (SystemSettings), normalised as rulesFrom() compares it. */
    private static function systemDefault(string $name): string
    {
        $defaults = ['mediums' => implode("\n", MediumList::DEFAULTS), 'separator' => Convention::SEPARATOR_ANY, 'namePattern' => ''];

        return $defaults[$name] ?? '';
    }

    /** @return array<string, mixed> */
    private static function systemValues(): array
    {
        $settings = StaticContainer::get(SystemSettings::class);
        return [
            'mediums' => $settings->mediums->getValue(),
            'separator' => $settings->separator->getValue(),
            'namePattern' => $settings->namePattern->getValue(),
            'sourceAliases' => $settings->sourceAliases->getValue(),
            'typoHints' => $settings->typoHints->getValue(),
        ];
    }

    private static function isSeparator(string $value): bool
    {
        return in_array($value, [Convention::SEPARATOR_ANY, Convention::SEPARATOR_HYPHEN, Convention::SEPARATOR_UNDERSCORE], true);
    }

    /** @param mixed $value */
    private static function flag($value): bool
    {
        return !in_array($value, [false, 0, '0', '', null], true);
    }
}
