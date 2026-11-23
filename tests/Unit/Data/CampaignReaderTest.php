<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Analyzer;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Convention;
use Piwik\Plugins\FeinwerkCampaignScore\Data\CampaignReader;

/**
 * Turning Matomo's report tables into CampaignData. Uses Matomo's DataTable classes (no database).
 *
 * @group FeinwerkCampaignScore
 */
class CampaignReaderTest extends TestCase
{
    /** @var list<array{0: string, 1: array}> */
    private $calls = [];

    /**
     * @param array<string|int, int|array{0: int, 1: DataTable}> $rows label => visits, or label => [visits, subtable]
     */
    private static function table(array $rows, ?int $others = null): DataTable
    {
        $table = new DataTable();
        foreach ($rows as $label => $row) {
            [$visits, $subtable] = is_array($row) ? $row : [$row, null];
            $added = new Row([Row::COLUMNS => ['label' => (string) $label, 'nb_visits' => $visits]]);
            if ($subtable !== null) {
                $added->setSubtable($subtable);
            }
            $table->addRow($added);
        }
        if ($others !== null) {
            $table->addSummaryRow(new Row([Row::COLUMNS => ['label' => DataTable::LABEL_SUMMARY_ROW, 'nb_visits' => $others]]));
        }
        return $table;
    }

    private static function types(int $campaignVisits): DataTable
    {
        $table = new DataTable();
        $table->addRow(new Row([Row::COLUMNS => ['label' => 'Direct Entry', 'nb_visits' => 999], Row::METADATA => ['referrer_type' => 1]]));
        $table->addRow(new Row([Row::COLUMNS => ['label' => 'Campaigns', 'nb_visits' => $campaignVisits], Row::METADATA => ['referrer_type' => 6]]));
        return $table;
    }

    /**
     * @param array<string, DataTable\DataTableInterface> $responses API method => table
     */
    private function reader(array $responses, bool $withMcr): CampaignReader
    {
        return new CampaignReader(function (string $method, array $params) use ($responses) {
            $this->calls[] = [$method, $params];
            if (!isset($responses[$method])) {
                throw new \RuntimeException('unexpected request ' . $method);
            }
            return $responses[$method];
        }, $withMcr);
    }

    public function test_withoutMarketingCampaignsReporting_readsNamesAndKeywordsOfMatomosCampaignReport(): void
    {
        $reader = $this->reader([
            'Referrers.getReferrerType' => self::types(100),
            'Referrers.getCampaigns' => self::table([
                'spring-sale' => [60, self::table(['Blue Shoes' => 3, '' => 57])],
                'Black Friday' => 30,
                '__discarded_by_policy__' => 4,
            ], 6),
        ], false);

        $result = $reader->read(3, 'month', '2026-10-01', '');

        $this->assertSame([''], array_keys($result));
        $data = $result['']['data'];
        $this->assertSame(100, $data->campaignVisits);
        $this->assertFalse($data->withSourceMedium);
        $this->assertSame(['spring-sale' => 60, 'Black Friday' => 30], $data->fields['name']);
        $this->assertSame(['Blue Shoes' => 3], $data->fields['keyword']);
        $this->assertSame(['name' => 10], $data->fieldOthers, 'Others row and masked values');
        $this->assertSame(10, $data->uncheckedVisits);
        $this->assertSame([
            ['name' => 'spring-sale', 'source' => null, 'medium' => null, 'visits' => 60],
            ['name' => 'Black Friday', 'source' => null, 'medium' => null, 'visits' => 30],
        ], $data->units);
    }

    public function test_requests_askForAllRowsWithoutGenericFilters(): void
    {
        $this->reader([
            'Referrers.getReferrerType' => self::types(1),
            'Referrers.getCampaigns' => self::table(['a' => 1]),
        ], false)->read(3, 'day', 'yesterday', 'browserCode==FF');

        $this->assertSame(['Referrers.getReferrerType', 'Referrers.getCampaigns'], array_column($this->calls, 0));
        foreach ($this->calls as [$method, $params]) {
            $this->assertSame(3, $params['idSite']);
            $this->assertSame('day', $params['period']);
            $this->assertSame('yesterday', $params['date']);
            $this->assertSame('browserCode==FF', $params['segment']);
            $this->assertSame(-1, $params['filter_limit']);
            $this->assertSame(1, $params['disable_generic_filters']);
        }
        $this->assertSame(1, $this->calls[1][1]['expanded']);
    }

    public function test_withMarketingCampaignsReporting_readsSourceMediumAndNameTogether(): void
    {
        $reader = $this->reader([
            'Referrers.getReferrerType' => self::types(200),
            'MarketingCampaignsReporting.getName' => self::table(['spring-sale' => 190], 10),
            'MarketingCampaignsReporting.getKeyword' => self::table([]),
            'MarketingCampaignsReporting.getContent' => self::table(['Banner A' => 20]),
            'MarketingCampaignsReporting.getGroup' => self::table([]),
            'MarketingCampaignsReporting.getPlacement' => self::table(['top' => 5], 2),
            'MarketingCampaignsReporting.getSource' => self::table(['google' => 100, 'newsletter' => 50]),
            'MarketingCampaignsReporting.getMedium' => self::table(['cpc' => 100, 'Email' => 40]),
            'MarketingCampaignsReporting.getSourceMedium' => self::table([
                'google - cpc' => [100, self::table(['spring-sale' => 90], 10)],
                'newsletter - Email' => [40, self::table(['spring-sale' => 35])],
                'newsletter' => [10, self::table(['spring-sale' => 10])],
            ], 7),
        ], true);

        $data = $reader->read(3, 'month', '2026-10-01', '')['']['data'];

        $this->assertTrue($data->withSourceMedium);
        $this->assertSame(200, $data->campaignVisits);
        $this->assertSame(['google' => 100, 'newsletter' => 50], $data->fields['source']);
        $this->assertSame(['cpc' => 100, 'Email' => 40], $data->fields['medium']);
        $this->assertSame(['Banner A' => 20], $data->fields['content']);
        $this->assertSame(['top' => 5], $data->fields['placement']);
        $this->assertSame(
            ['name' => 10, 'keyword' => 0, 'source' => 0, 'medium' => 0, 'content' => 0, 'group' => 0, 'placement' => 2],
            $data->fieldOthers
        );
        $this->assertSame([
            ['name' => 'spring-sale', 'source' => 'google', 'medium' => 'cpc', 'visits' => 90],
            ['name' => 'spring-sale', 'source' => 'newsletter', 'medium' => 'Email', 'visits' => 35],
            ['name' => null, 'source' => 'newsletter', 'medium' => 'Email', 'visits' => 5],
            // only the source is set: "newsletter" is a source and no medium in the reports
            ['name' => 'spring-sale', 'source' => 'newsletter', 'medium' => null, 'visits' => 10],
        ], $data->units);
        $this->assertSame(17, $data->uncheckedVisits, 'the Others rows of a subtable and of the report');
    }

    /**
     * Campaign visits that have no value in any report of Marketing Campaigns Reporting were tracked before it was
     * activated: they are unknown, not missing values. Visits with a name but without source and medium are known.
     */
    public function test_visitsUnknownToMarketingCampaignsReporting_areCountedApart(): void
    {
        $reader = $this->reader([
            'Referrers.getReferrerType' => self::types(150),
            'MarketingCampaignsReporting.getName' => self::table(['a' => 40]),
            'MarketingCampaignsReporting.getKeyword' => self::table([]),
            'MarketingCampaignsReporting.getContent' => self::table([]),
            'MarketingCampaignsReporting.getGroup' => self::table([]),
            'MarketingCampaignsReporting.getPlacement' => self::table([]),
            'MarketingCampaignsReporting.getSource' => self::table(['google' => 30]),
            'MarketingCampaignsReporting.getMedium' => self::table(['cpc' => 25]),
            'MarketingCampaignsReporting.getSourceMedium' => self::table([
                'google - cpc' => [25, self::table(['a' => 25])],
                'google' => [5, self::table(['a' => 5])],
            ]),
        ], true);

        $data = $reader->read(1, 'month', '2026-11-01', '')['']['data'];

        // known: 30 visits with source or medium, plus 10 with only a name
        $this->assertSame(110, $data->unknownVisits);
        $this->assertSame(0, $data->uncheckedVisits);
    }

    public function test_aSinglePartThatIsBothASourceAndAMedium_staysUnknown(): void
    {
        $reader = $this->reader([
            'Referrers.getReferrerType' => self::types(5),
            'MarketingCampaignsReporting.getName' => self::table(['a' => 5]),
            'MarketingCampaignsReporting.getKeyword' => self::table([]),
            'MarketingCampaignsReporting.getContent' => self::table([]),
            'MarketingCampaignsReporting.getGroup' => self::table([]),
            'MarketingCampaignsReporting.getPlacement' => self::table([]),
            'MarketingCampaignsReporting.getSource' => self::table(['email' => 3, 'x' => 2]),
            'MarketingCampaignsReporting.getMedium' => self::table(['email' => 2]),
            'MarketingCampaignsReporting.getSourceMedium' => self::table(['email' => [3, self::table(['a' => 3])], 'x - email' => [2, self::table(['a' => 2])]]),
        ], true);

        $units = $reader->read(1, 'day', '2026-11-01', '')['']['data']->units;

        $this->assertSame(['name' => 'a', 'source' => null, 'medium' => null, 'visits' => 3], $units[0]);
    }

    public function test_maskedValuesOfMarketingCampaignsReporting_cannotBeChecked(): void
    {
        $masked = '(UTM parameter discarded)';
        $reader = new CampaignReader(static function (string $method) use ($masked) {
            switch ($method) {
                case 'Referrers.getReferrerType':
                    return self::types(10);
                case 'MarketingCampaignsReporting.getSourceMedium':
                    return self::table([$masked . ' - ' . $masked => [10, self::table([$masked => 10])]]);
                default:
                    return self::table([$masked => 10]);
            }
        }, true, ['__discarded_by_policy__', $masked]);

        $data = $reader->read(1, 'day', 'today', '')['']['data'];

        $this->assertSame([], $data->units);
        $this->assertSame(10, $data->uncheckedVisits);
        $this->assertSame([], $data->fields['name']);
        $this->assertSame(
            ['name' => 10, 'keyword' => 10, 'source' => 10, 'medium' => 10, 'content' => 10, 'group' => 10, 'placement' => 10],
            $data->fieldOthers
        );
    }

    public function test_aPeriodWithoutDataOfMarketingCampaignsReporting_fallsBackToMatomosCampaignReport(): void
    {
        $reader = $this->reader([
            'Referrers.getReferrerType' => self::types(5),
            'MarketingCampaignsReporting.getName' => self::table([]),
            'MarketingCampaignsReporting.getKeyword' => self::table([]),
            'MarketingCampaignsReporting.getContent' => self::table([]),
            'MarketingCampaignsReporting.getGroup' => self::table([]),
            'MarketingCampaignsReporting.getPlacement' => self::table([]),
            'MarketingCampaignsReporting.getSource' => self::table([]),
            'MarketingCampaignsReporting.getMedium' => self::table([]),
            'MarketingCampaignsReporting.getSourceMedium' => self::table([]),
            'Referrers.getCampaigns' => self::table(['older-campaign' => 5]),
        ], true);

        $data = $reader->read(1, 'year', '2020-01-01', '')['']['data'];

        $this->assertFalse($data->withSourceMedium);
        $this->assertSame(['older-campaign' => 5], $data->fields['name']);
    }

    /**
     * The overview of all websites asks each report once for all websites (Matomo answers with one table per website).
     * Referrers.getReferrerType takes one website only, so the campaign visits are the sum of Matomo's campaign report.
     */
    public function test_severalWebsites_areReadWithOneRequestPerReport(): void
    {
        $map = static function (array $tables): DataTable\Map {
            $map = new DataTable\Map();
            $map->setKeyName('idSite');
            foreach ($tables as $idSite => $table) {
                $map->addTable($table, (string) $idSite);
            }
            return $map;
        };
        $reader = $this->reader([
            'Referrers.getCampaigns' => $map([1 => self::table(['a' => 3], 2), 4 => self::table(['Black Friday' => 5])]),
        ], false);

        $sites = $reader->readSites([1, 4], 'month', '2026-11-01', '');

        $this->assertSame([1, 4], array_keys($sites));
        $this->assertSame(5, $sites[1]->campaignVisits, 'the Others row counts');
        $this->assertSame(['Black Friday' => 5], $sites[4]->fields['name']);
        $this->assertSame(['Referrers.getCampaigns'], array_column($this->calls, 0));
        $this->assertSame('1,4', $this->calls[0][1]['idSite']);
    }

    /**
     * Load test 2026-11-13: the overview read Matomo's campaign report with every keyword for every website, also
     * when Marketing Campaigns Reporting had the values. The visits come from the report without keywords now; the
     * keywords are read only for the websites that need Matomo's report.
     */
    public function test_severalWebsites_readKeywordsOnlyForWebsitesWithoutMarketingCampaignsReporting(): void
    {
        $map = static function (array $tables): DataTable\Map {
            $map = new DataTable\Map();
            $map->setKeyName('idSite');
            foreach ($tables as $idSite => $table) {
                $map->addTable($table, (string) $idSite);
            }
            return $map;
        };
        $mcrSite = static function (array $rows) use ($map): DataTable\Map {
            // website 1 has data of Marketing Campaigns Reporting, website 4 none (tracked before it was activated)
            return $map([1 => self::table($rows), 4 => self::table([])]);
        };
        $responses = [
            'Referrers.getCampaigns' => $map([1 => self::table(['spring-sale' => 10]), 4 => self::table(['Black Friday' => 5])]),
            'MarketingCampaignsReporting.getName' => $mcrSite(['spring-sale' => 10]),
            'MarketingCampaignsReporting.getKeyword' => $mcrSite([]),
            'MarketingCampaignsReporting.getContent' => $mcrSite([]),
            'MarketingCampaignsReporting.getGroup' => $mcrSite([]),
            'MarketingCampaignsReporting.getPlacement' => $mcrSite([]),
            'MarketingCampaignsReporting.getSource' => $mcrSite(['google' => 10]),
            'MarketingCampaignsReporting.getMedium' => $mcrSite(['cpc' => 10]),
            'MarketingCampaignsReporting.getSourceMedium' => $mcrSite(['google - cpc' => [10, self::table(['spring-sale' => 10])]]),
        ];
        $expanded = self::table(['Black Friday' => [5, self::table(['Wanderschuhe' => 5])]]);
        $reader = new CampaignReader(function (string $method, array $params) use ($responses, $expanded) {
            $this->calls[] = [$method, $params];
            if ($method === 'Referrers.getCampaigns' && !empty($params['expanded'])) {
                return $expanded;
            }
            return $responses[$method];
        }, true);

        $sites = $reader->readSites([1, 4], 'day', '2026-11-05', '');

        $campaignCalls = array_values(array_filter($this->calls, static function (array $call): bool {
            return $call[0] === 'Referrers.getCampaigns';
        }));
        $this->assertCount(2, $campaignCalls);
        $this->assertArrayNotHasKey('expanded', $campaignCalls[0][1], 'the visits of all websites come without keywords');
        $this->assertSame('1,4', $campaignCalls[0][1]['idSite']);
        $this->assertSame(1, $campaignCalls[1][1]['expanded']);
        $this->assertSame('4', (string) $campaignCalls[1][1]['idSite'], 'keywords only for the website without data of Marketing Campaigns Reporting');
        $this->assertTrue($sites[1]->withSourceMedium);
        $this->assertSame(['Black Friday' => 5], $sites[4]->fields['name']);
        $this->assertSame(['Wanderschuhe' => 5], $sites[4]->fields['keyword']);
    }

    public function test_severalWebsites_withMarketingCampaignsReportingEverywhere_readNoKeywordsOfMatomosReport(): void
    {
        $map = static function (DataTable $table): DataTable\Map {
            $map = new DataTable\Map();
            $map->setKeyName('idSite');
            $map->addTable($table, '1');
            $map->addTable(clone $table, '2');
            return $map;
        };
        $responses = ['Referrers.getCampaigns' => $map(self::table(['spring-sale' => 10]))];
        foreach (['getName', 'getKeyword', 'getContent', 'getGroup', 'getPlacement', 'getSource', 'getMedium'] as $method) {
            $responses['MarketingCampaignsReporting.' . $method] = $map(self::table($method === 'getSource' ? ['google' => 10] : []));
        }
        $responses['MarketingCampaignsReporting.getSourceMedium'] = $map(self::table(['google' => [10, self::table(['spring-sale' => 10])]]));

        $this->reader($responses, true)->readSites([1, 2], 'day', '2026-11-05', '');

        $expanded = array_filter($this->calls, static function (array $call): bool {
            return $call[0] === 'Referrers.getCampaigns' && !empty($call[1]['expanded']);
        });
        $this->assertSame([], $expanded);
    }

    /** @return array<string, DataTable> */
    private static function mcrResponses(): array
    {
        return [
            'Referrers.getReferrerType' => self::types(200),
            'MarketingCampaignsReporting.getName' => self::table(['spring-sale' => 150, 'Spring Sale' => 40], 10),
            'MarketingCampaignsReporting.getKeyword' => self::table(['Wanderschuhe' => 30]),
            'MarketingCampaignsReporting.getContent' => self::table(['Banner A' => 20]),
            'MarketingCampaignsReporting.getGroup' => self::table(['Winter' => 5]),
            'MarketingCampaignsReporting.getPlacement' => self::table(['top' => 5], 2),
            'MarketingCampaignsReporting.getSource' => self::table(['google' => 100, 'fb' => 50]),
            'MarketingCampaignsReporting.getMedium' => self::table(['cpc' => 100, 'Email' => 40]),
            'MarketingCampaignsReporting.getSourceMedium' => self::table([
                'google - cpc' => [100, self::table(['spring-sale' => 90], 10)],
                'fb - Email' => [40, self::table(['Spring Sale' => 35])],
            ], 7),
        ];
    }

    /**
     * Load test 2026-11-13: the score over time read every report for every period. The score needs only campaign
     * name, source and medium (keyword, content, group and placement are checked but not scored).
     */
    public function test_scoreOnly_withMarketingCampaignsReporting_readsOnlyTheScoredReportsAndGivesTheSameScore(): void
    {
        $full = $this->reader(self::mcrResponses(), true)->read(3, 'day', '2026-11-05', '')['']['data'];
        $this->calls = [];
        $scoreOnly = $this->reader(self::mcrResponses(), true)->read(3, 'day', '2026-11-05', '', true)['']['data'];

        $this->assertSame([
            'Referrers.getReferrerType',
            'MarketingCampaignsReporting.getName',
            'MarketingCampaignsReporting.getSource',
            'MarketingCampaignsReporting.getMedium',
            'MarketingCampaignsReporting.getSourceMedium',
        ], array_column($this->calls, 0));
        $fullResult = Analyzer::analyze($full, Convention::defaults());
        $scoreResult = Analyzer::analyze($scoreOnly, Convention::defaults());
        $this->assertNotNull($fullResult->score);
        $this->assertSame($fullResult->score, $scoreResult->score);
        $this->assertSame($fullResult->cleanVisits, $scoreResult->cleanVisits);
        $this->assertSame($fullResult->checkedVisits, $scoreResult->checkedVisits);
        $this->assertSame($fullResult->uncheckedVisits, $scoreResult->uncheckedVisits);
    }

    public function test_scoreOnly_withoutMarketingCampaignsReporting_readsMatomosCampaignReportWithoutKeywords(): void
    {
        $withKeywords = self::table(['spring-sale' => [8, self::table(['Wanderschuhe' => 8])], 'Spring Sale' => [2, self::table(['x' => 2])]]);
        $full = $this->reader(['Referrers.getReferrerType' => self::types(10), 'Referrers.getCampaigns' => $withKeywords], false)
            ->read(3, 'day', '2026-11-05', '')['']['data'];
        $this->calls = [];
        $scoreOnly = $this->reader(['Referrers.getReferrerType' => self::types(10), 'Referrers.getCampaigns' => self::table(['spring-sale' => 8, 'Spring Sale' => 2])], false)
            ->read(3, 'day', '2026-11-05', '', true)['']['data'];

        $this->assertArrayNotHasKey('expanded', $this->calls[1][1]);
        $this->assertSame(
            Analyzer::analyze($full, Convention::defaults())->score,
            Analyzer::analyze($scoreOnly, Convention::defaults())->score
        );
    }

    public function test_campaignNames_countTheRowsOfMatomosCampaignReportWithoutKeywords(): void
    {
        $map = new DataTable\Map();
        $map->setKeyName('idSite');
        $map->addTable(self::table(['a' => 3, 'b' => 2], 9), '1');
        $map->addTable(self::table([]), '4');
        $reader = $this->reader(['Referrers.getCampaigns' => $map], true);

        $this->assertSame([1 => 2, 4 => 0], $reader->campaignNames([1, 4], 'day', '2026-11-05', ''));
        $this->assertArrayNotHasKey('expanded', $this->calls[0][1]);
        $this->assertSame('1,4', $this->calls[0][1]['idSite']);
    }

    public function test_aSingleWebsite_inTheOverviewIsReadAsWell(): void
    {
        $reader = $this->reader(['Referrers.getCampaigns' => self::table(['a' => 3])], false);

        $this->assertSame([7], array_keys($reader->readSites([7], 'day', '2026-11-01', '')));
    }

    public function test_severalPeriods_giveOneResultPerPeriodWithItsMetadata(): void
    {
        $map = static function (array $tables): DataTable\Map {
            $map = new DataTable\Map();
            $map->setKeyName('date');
            foreach ($tables as $label => $table) {
                $table->setMetadata('fwcs_test', $label);
                $map->addTable($table, $label);
            }
            return $map;
        };
        $reader = $this->reader([
            'Referrers.getReferrerType' => $map(['2026-11-01' => self::types(3), '2026-11-02' => self::types(0)]),
            'Referrers.getCampaigns' => $map(['2026-11-01' => self::table(['a' => 3]), '2026-11-02' => self::table([])]),
        ], false);

        $result = $reader->read(1, 'day', '2026-11-01,2026-11-02', '');

        $this->assertSame(['2026-11-01', '2026-11-02'], array_keys($result));
        $this->assertSame(3, $result['2026-11-01']['data']->campaignVisits);
        $this->assertSame(0, $result['2026-11-02']['data']->campaignVisits);
        $this->assertSame('2026-11-02', $result['2026-11-02']['metadata']['fwcs_test']);
    }
}
