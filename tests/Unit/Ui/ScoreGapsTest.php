<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Ui;

use PHPUnit\Framework\TestCase;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\ScoreGaps;

/**
 * Campaign Score over time shows a gap for a period without campaign visits instead of 0 % (Peter, 2026-11-14).
 *
 * @group FeinwerkCampaignScore
 */
class ScoreGapsTest extends TestCase
{
    /** @param list<float|int|null> $scores null: a period without campaign visits (no row) */
    private static function map(array $scores): DataTable\Map
    {
        $map = new DataTable\Map();
        $map->setKeyName('date');
        foreach ($scores as $i => $score) {
            $table = new DataTable();
            if ($score !== null) {
                $table->addRow(new Row([Row::COLUMNS => ['label' => 'score', 'score' => $score]]));
            }
            $map->addTable($table, '2026-0' . ($i + 1));
        }

        return $map;
    }

    private static function graph(array $values): array
    {
        return ['params' => ['axes' => [], 'series' => [['label' => 'Campaign Score', 'internalLabel' => 'Campaign Score']]], 'data' => [$values]];
    }

    public function test_aPeriodWithoutAScoreIsMissingAndAZeroScoreIsNot(): void
    {
        $this->assertSame([false, true, false], ScoreGaps::missing(self::map([83.5, null, 0])));
        $this->assertSame([], ScoreGaps::missing(new DataTable()));
    }

    public function test_theGraphHasNoPointWhereTheScoreIsMissing(): void
    {
        $graph = ScoreGaps::apply(self::graph([83.5, 0, 0]), [false, true, false], 'Campaign Score');

        $this->assertSame([83.5, null, 0], $graph['data'][0]);
        // no breakOnNull: Matomo's graph would draw the null points at the top
        $this->assertArrayNotHasKey('breakOnNull', $graph['params']['series'][0]);
    }

    /** Matomo names a series by the column's translation ("Campaign Score"), not by the column. */
    public function test_onlyTheScoreSeriesGetsTheGaps(): void
    {
        $graph = ['params' => ['series' => [['internalLabel' => 'Campaign Score'], ['internalLabel' => 'Visits']]], 'data' => [[80, 0], [10, 0]]];

        $applied = ScoreGaps::apply($graph, [false, true], 'Campaign Score');
        $this->assertSame([80, null], $applied['data'][0]);
        $this->assertSame([10, 0], $applied['data'][1]);

        // without the label several series stay as they are; a single series is the score
        $this->assertSame($graph, ScoreGaps::apply($graph, [false, true]));
        $this->assertSame([80, null], ScoreGaps::apply(self::graph([80, 0]), [false, true])['data'][0]);
    }

    public function test_theGraphStaysAsItIsWithoutGapsOrWhenThePointsDoNotMatch(): void
    {
        $graph = self::graph([83.5, 90, 70]);

        $this->assertSame($graph, ScoreGaps::apply($graph, [false, false, false]));
        $this->assertSame($graph, ScoreGaps::apply($graph, [false, true]));
        $this->assertSame(['data' => 'not a list'], ScoreGaps::apply(['data' => 'not a list'], [true]));
    }

    /** Matomo would add an "Evolution over the period" button for this graph; its own evolution graph has none. */
    public function test_theFooterLosesOnlyTheExtraGraphButton(): void
    {
        $groups = [
            ['class' => 'tableAllColumnsSwitch', 'buttons' => [['id' => 'tableAllColumns']]],
            ['class' => 'tableGraphViews', 'buttons' => [['id' => 'graphEvolution', 'title' => 'Evolution over the period']]],
        ];
        $this->assertSame([$groups[0]], ScoreGaps::footerIcons($groups, 'graphEvolution'));

        $mixed = [['class' => 'tableGraphViews', 'buttons' => [['id' => 'graphVerticalBar'], ['id' => 'graphEvolution']]]];
        $this->assertSame([['class' => 'tableGraphViews', 'buttons' => [['id' => 'graphVerticalBar']]]], ScoreGaps::footerIcons($mixed, 'graphEvolution'));
        $this->assertSame([], ScoreGaps::footerIcons([], 'graphEvolution'));
    }
}
