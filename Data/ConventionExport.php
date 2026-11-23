<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Data;

use Piwik\Plugins\FeinwerkCampaignScore\Check\CampaignData;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Convention;
use Piwik\Plugins\FeinwerkCampaignScore\Check\FieldChecker;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Finding;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Result;
use Piwik\Plugins\FeinwerkCampaignScore\Check\ValueRules;

/**
 * The clean campaign values of a website as a starting naming convention for Feinwerk Campaigns: format
 * "feinwerk-campaign-convention", version 1, agreed with the Feinwerk Campaigns team on 2026-11-13. Counts only, no
 * visitor data; the admin downloads the file and imports it there (nothing is sent).
 */
final class ConventionExport
{
    /** the page of Feinwerk Campaigns that takes the file as a naming convention suggestion (it carries no data) */
    public const IMPORT_URL = 'https://app.campaigns.feinwerk.io/convention/suggest';

    public const FORMAT = 'feinwerk-campaign-convention';
    public const VERSION = 1;
    public const MAX_VALUES = 200;

    /** field => Matomo dimension name in the file */
    public const FIELDS = [
        'name' => 'campaignName',
        'source' => 'campaignSource',
        'medium' => 'campaignMedium',
        'keyword' => 'campaignKeyword',
        'content' => 'campaignContent',
        'group' => 'campaignGroup',
        'placement' => 'campaignPlacement',
    ];

    /**
     * @param array{plugin_version: string, matomo_version: string, site_id: int, site_name: string,
     *              site_urls: list<string>, period: array{from: string, to: string}} $source
     * @return array<string, mixed>
     */
    public static function build(CampaignData $data, Result $result, Convention $convention, array $source, string $generatedAt): array
    {
        $replace = [];
        foreach ($result->findings as $finding) {
            if (in_array($finding->type, [Finding::VARIANTS, Finding::SIMILAR], true)) {
                foreach ($finding->valuesToFix() as $value) {
                    $replace[$finding->field][$value] = true;
                }
            }
        }

        $fields = [];
        $hyphens = 0;
        $underscores = 0;
        foreach (self::FIELDS as $field => $dimension) {
            $clean = [];
            foreach ($data->fields[$field] ?? [] as $value => $visits) {
                $value = (string) $value;
                if (
                    $value === ''
                    || !ValueRules::isClean($value)
                    || isset($replace[$field][$value])
                    || !FieldChecker::isCleanValue($field, $value, $convention)
                ) {
                    continue;
                }
                $clean[] = ['value' => $value, 'visits' => (int) $visits];
                $hyphens += substr_count($value, '-');
                $underscores += substr_count($value, '_');
            }
            usort($clean, static function (array $a, array $b): int {
                return [$b['visits'], $a['value']] <=> [$a['visits'], $b['value']];
            });
            $fields[$dimension] = array_slice($clean, 0, self::MAX_VALUES);
        }

        if ($convention->separator !== Convention::SEPARATOR_ANY) {
            $separator = $convention->separator;
        } elseif ($hyphens > 0 && $underscores === 0) {
            $separator = '-';
        } elseif ($underscores > 0 && $hyphens === 0) {
            $separator = '_';
        } else {
            $separator = null;
        }

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'generated_at' => $generatedAt,
            'source' => [
                'product' => 'Feinwerk Campaign Score',
                'plugin_version' => $source['plugin_version'],
                'matomo_version' => $source['matomo_version'],
                'site_id' => $source['site_id'],
                'site_name' => $source['site_name'],
                'site_urls' => self::origins($source['site_urls']),
                'period' => $source['period'],
            ],
            // Matomo's reports do not keep whether a value came from mtm_, utm_ or pk_ parameters
            'parameter_style' => null,
            'separator' => $separator,
            // without Marketing Campaigns Reporting Matomo lowercases names and keywords: the original case is unknown
            'case' => $data->withSourceMedium ? 'lower' : null,
            'allowed_mediums' => $convention->mediums->values(),
            'fields' => $fields,
        ];
    }

    public static function fileName(int $idSite, string $day): string
    {
        return 'feinwerk-campaign-convention-' . $idSite . '-' . $day . '.json';
    }

    /**
     * @param list<string> $urls
     * @return list<string> scheme://host[:port], no path, no query
     */
    private static function origins(array $urls): array
    {
        $origins = [];
        foreach ($urls as $url) {
            $parts = parse_url((string) $url);
            if (!is_array($parts) || empty($parts['host'])) {
                continue;
            }
            $origin = ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
            $origins[$origin] = true;
        }
        return array_keys($origins);
    }
}
