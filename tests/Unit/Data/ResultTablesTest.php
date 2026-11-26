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
use Piwik\Plugins\FeinwerkCampaignScore\Check\Finding;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Result;
use Piwik\Plugins\FeinwerkCampaignScore\Data\ResultTables;

/**
 * The API's tables: one score row per period, one row per finding.
 *
 * @group FeinwerkCampaignScore
 */
class ResultTablesTest extends TestCase
{
    private static function sampleResult(?float $score = 0.8125): Result
    {
        return new Result($score, 650, 800, 850, 50, true, [
            new Finding(Finding::INCOMPLETE, 'medium', [], 60, null),
            new Finding(Finding::VARIANTS, 'name', ['Newsletter' => 30, 'newsletter' => 20], 50, 'newsletter'),
            new Finding(Finding::FORMAT, 'source', [2026 => 4], 4, null),
        ]);
    }

    public function test_score_isOneRowWithThePercentageAndItsBasis(): void
    {
        $table = ResultTables::score(self::sampleResult());

        $this->assertSame(1, $table->getRowsCount());
        $this->assertSame([
            'score' => 81.3,
            'clean_visits' => 650,
            'checked_visits' => 800,
            'campaign_visits' => 850,
            'unchecked_visits' => 50,
            'with_source_medium' => 1,
            'findings' => 3,
        ], $table->getFirstRow()->getColumns());
    }

    /** Several periods are read for the score only: a count of some findings would mislead. */
    public function test_score_forSeveralPeriods_hasNoFindingsCount(): void
    {
        $columns = ResultTables::score(self::sampleResult(), false)->getFirstRow()->getColumns();

        $this->assertArrayNotHasKey('findings', $columns);
        $this->assertSame(81.3, $columns['score']);
    }

    public function test_score_leavesTheScoreOutWhenThereIsNone(): void
    {
        $columns = ResultTables::score(self::sampleResult(null))->getFirstRow()->getColumns();

        $this->assertArrayNotHasKey('score', $columns);
        $this->assertSame(800, $columns['checked_visits']);
    }

    public function test_findings_areOneRowEachWithTypeFieldVisitsAndSuggestion(): void
    {
        $rows = ResultTables::findings(self::sampleResult())->getRows();

        $this->assertCount(3, $rows);
        // texts are metadata, not columns: Matomo sums all columns of a table for its totals row
        // score_gain: points this fix alone adds (the sample's findings carry none)
        $this->assertSame(['label' => '', 'nb_visits' => 60, 'score_gain' => 0.0], $rows[0]->getColumns());
        $this->assertSame([
            'type' => 'incomplete',
            'field' => 'medium',
            'suggestion' => '',
            'values' => [],
            'contexts' => [],
            'new' => 0,
            'accept' => [],
            'segment' => 'referrerType==campaign;campaignMedium==',
        ], $rows[0]->getMetadata());
        $this->assertSame(['Newsletter'], $rows[1]->getMetadata('accept'), 'the row action accepts the spellings to replace');
        $this->assertSame(['label' => 'Newsletter, newsletter', 'nb_visits' => 50, 'score_gain' => 0.0], $rows[1]->getColumns());
        $this->assertSame('variants', $rows[1]->getMetadata('type'));
        $this->assertSame('newsletter', $rows[1]->getMetadata('suggestion'));
        $this->assertSame(['Newsletter' => 30, 'newsletter' => 20], $rows[1]->getMetadata('values'));
        $this->assertSame('referrerType==campaign;campaignName==Newsletter', $rows[1]->getMetadata('segment'), 'only the spelling to replace');
        $this->assertSame('2026', $rows[2]->getColumn('label'));
    }

    /**
     * Regression: Matomo's API sums every column of a table into a totals row (ReportTotalsCalculator); text columns
     * made it log "Trying to add two strings" (seen in the sandbox on Matomo 5.0.0). Besides the label, only numbers.
     */
    public function test_findings_haveOnlyNumbersBesidesTheLabel_soMatomosTotalsRowCanSumThem(): void
    {
        foreach ([ResultTables::findings(self::sampleResult()), ResultTables::score(self::sampleResult())] as $table) {
            foreach ($table->getRows() as $row) {
                foreach ($row->getColumns() as $column => $value) {
                    if ($column !== 'label') {
                        $this->assertTrue(is_int($value) || is_float($value), $column . ' is no number');
                    }
                }
            }
        }
    }

    public function test_score_carriesThePreviousScoreWhenKnown(): void
    {
        $result = new Result(0.8, 8, 10, 10, 0, true, [], 0.75);

        $this->assertSame(75.0, ResultTables::score($result)->getFirstRow()->getColumn('previous_score'));
    }

    public function test_findings_carryContextsAndTheNewMark(): void
    {
        $context = ['name' => 'winter-sale', 'source' => 'facebook', 'medium' => 'Facebook Ads', 'visits' => 15];
        $finding = new Finding(Finding::UNUSUAL_MEDIUM, 'medium', ['Facebook Ads' => 15], 15, null, [$context], true);
        $row = ResultTables::findings(new Result(0.5, 1, 2, 2, 0, true, [$finding]))->getFirstRow();

        $this->assertSame([$context], $row->getMetadata('contexts'));
        $this->assertSame(1, $row->getMetadata('new'));
    }

    public function test_corrections_areOneRowPerValueToFix(): void
    {
        $rows = ResultTables::corrections(self::sampleResult())->getRows();

        $this->assertSame(
            [['label' => 'Newsletter', 'nb_visits' => 30], ['label' => '2026', 'nb_visits' => 4]],
            array_map(static function ($row) {
                return $row->getColumns();
            }, $rows)
        );
        $this->assertSame(['field' => 'name', 'type' => 'variants', 'suggestion' => 'newsletter'], $rows[0]->getMetadata());
    }

    public function test_output_isATableForOnePeriodAndAMapForSeveral(): void
    {
        $one = ResultTables::output(['' => ['metadata' => ['fwcs_test' => 'x'], 'result' => self::sampleResult()]], [ResultTables::class, 'score']);
        $this->assertInstanceOf(DataTable::class, $one);
        $this->assertSame('x', $one->getMetadata('fwcs_test'));

        $map = ResultTables::output([
            '2026-11-01' => ['metadata' => [], 'result' => self::sampleResult()],
            '2026-11-02' => ['metadata' => [], 'result' => self::sampleResult(null)],
        ], [ResultTables::class, 'score']);
        $this->assertInstanceOf(DataTable\Map::class, $map);
        $this->assertSame(['2026-11-01', '2026-11-02'], array_keys($map->getDataTables()));
    }

    public function test_score_has_the_field_shares_and_the_fixed_findings(): void
    {
        $result = new Result(0.5, 1, 2, 2, 0, true, [], 0.4, ['name' => 1.0, 'source' => 0.754, 'medium' => null], 2);
        $columns = ResultTables::score($result)->getFirstRow()->getColumns();
        $this->assertSame(100.0, $columns['score_name']);
        $this->assertSame(75.4, $columns['score_source']);
        $this->assertArrayNotHasKey('score_medium', $columns, 'no visits, no share');
        $this->assertSame(2, $columns['fixed_findings']);

        $several = ResultTables::score($result, false)->getFirstRow()->getColumns();
        $this->assertArrayNotHasKey('fixed_findings', $several, 'several periods: no comparison');
        $this->assertSame(100.0, $several['score_name']);
    }
}
