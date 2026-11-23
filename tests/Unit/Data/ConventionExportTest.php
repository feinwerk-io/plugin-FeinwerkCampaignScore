<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Analyzer;
use Piwik\Plugins\FeinwerkCampaignScore\Check\CampaignData;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Convention;
use Piwik\Plugins\FeinwerkCampaignScore\Check\MediumList;
use Piwik\Plugins\FeinwerkCampaignScore\Data\ConventionExport;

/**
 * The starting convention for Feinwerk Campaigns (format feinwerk-campaign-convention, version 1, agreed with the
 * CampaignsApp session on 2026-11-13).
 *
 * @group FeinwerkCampaignScore
 */
class ConventionExportTest extends TestCase
{
    private const SOURCE = [
        'plugin_version' => '6.0.0-beta.2',
        'matomo_version' => '6.0.0-rc1',
        'site_id' => 3,
        'site_name' => 'Outdoor Shop',
        'site_urls' => ['https://shop.example/path?x=1', 'http://www.shop.example'],
        'period' => ['from' => '2026-11-01', 'to' => '2026-11-05'],
    ];

    private static function data(): CampaignData
    {
        return new CampaignData(
            200,
            true,
            [
                'name' => ['black-friday' => 120, 'Black Friday' => 30, 'news_letter' => 20, 'newsletter' => 50],
                'keyword' => ['wanderschuhe herren' => 40, 'trekking' => 10],
                'source' => ['google' => 150, 'fb' => 50],
                'medium' => ['cpc' => 150, 'Facebook Ads' => 50],
                'content' => [],
                'group' => ['winter' => 10],
                'placement' => [],
            ],
            [],
            [],
            0
        );
    }

    public function test_exportsTheCleanValuesPerMatomoDimensionMostVisitedFirst(): void
    {
        $convention = Convention::defaults();
        $data = self::data();
        $export = ConventionExport::build($data, Analyzer::analyze($data, $convention), $convention, self::SOURCE, '2026-11-13T10:00:00Z');

        $this->assertSame('feinwerk-campaign-convention', $export['format']);
        $this->assertSame(1, $export['version']);
        $this->assertSame('2026-11-13T10:00:00Z', $export['generated_at']);
        $this->assertSame('Feinwerk Campaign Score', $export['source']['product']);
        $this->assertSame(['https://shop.example', 'http://www.shop.example'], $export['source']['site_urls'], 'bare origins');
        $this->assertNull($export['parameter_style'], 'the reports do not keep the parameter prefix');
        $this->assertSame('lower', $export['case']);
        $this->assertSame(MediumList::DEFAULTS, $export['allowed_mediums']);
        $this->assertSame([
            'campaignName' => [['value' => 'black-friday', 'visits' => 120], ['value' => 'newsletter', 'visits' => 50]],
            'campaignSource' => [['value' => 'google', 'visits' => 150]],
            'campaignMedium' => [['value' => 'cpc', 'visits' => 150]],
            'campaignKeyword' => [['value' => 'trekking', 'visits' => 10]],
            'campaignContent' => [],
            'campaignGroup' => [['value' => 'winter', 'visits' => 10]],
            'campaignPlacement' => [],
        ], $export['fields']);
        $this->assertSame('-', $export['separator'], 'the clean values use only hyphens');
    }

    public function test_separatorAndCase_followTheRuleOrAreNullWhenUnclear(): void
    {
        $data = new CampaignData(10, false, ['name' => ['a_b' => 5, 'c-d' => 5], 'keyword' => []], [], [], 0);
        $any = Convention::defaults();
        $underscore = new Convention(MediumList::defaults(), [], Convention::SEPARATOR_UNDERSCORE);

        $this->assertNull(ConventionExport::build($data, Analyzer::analyze($data, $any), $any, self::SOURCE, 'now')['separator']);
        $this->assertSame('_', ConventionExport::build($data, Analyzer::analyze($data, $underscore), $underscore, self::SOURCE, 'now')['separator']);
        $this->assertNull(ConventionExport::build($data, Analyzer::analyze($data, $any), $any, self::SOURCE, 'now')['case'], 'without MCR');
    }

    public function test_atMost200ValuesPerField(): void
    {
        $names = [];
        for ($i = 0; $i < 250; $i++) {
            $names['campaign-' . $i] = 1000 - $i;
        }
        $data = new CampaignData(1, false, ['name' => $names, 'keyword' => []], [], [], 0);
        $convention = new Convention(MediumList::defaults(), [], Convention::SEPARATOR_ANY, null, true, false);

        $fields = ConventionExport::build($data, Analyzer::analyze($data, $convention), $convention, self::SOURCE, 'now')['fields'];

        $this->assertCount(200, $fields['campaignName']);
        $this->assertSame('campaign-0', $fields['campaignName'][0]['value']);
    }

    public function test_fileName(): void
    {
        $this->assertSame('feinwerk-campaign-convention-3-2026-11-13.json', ConventionExport::fileName(3, '2026-11-13'));
    }
}
