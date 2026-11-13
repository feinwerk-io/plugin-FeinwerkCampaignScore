<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Ui;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\ScoreUnit;

/**
 * Matomo 6 forecasts the open period of an evolution graph; without a unit it treated the score like a sum and drew a
 * projection far above 100 (seen on stats6, 2026-11-13). The score is a percentage in its own report only: "score" is a
 * generic column name other plugins may use.
 *
 * @group FeinwerkCampaignScore
 */
class ScoreUnitTest extends TestCase
{
    public function test_theScoreOfTheReportIsAPercentage(): void
    {
        $this->assertSame('%', ScoreUnit::forColumn('score', ['module' => 'FeinwerkCampaignScore', 'action' => 'getScore']));
    }

    public function test_alsoInGraphImagesAndEmbeddedWidgets(): void
    {
        $this->assertSame('%', ScoreUnit::forColumn('score', ['module' => 'API', 'method' => 'ImageGraph.get', 'apiModule' => 'FeinwerkCampaignScore', 'apiAction' => 'getScore']));
        $this->assertSame('%', ScoreUnit::forColumn('score', ['module' => 'Widgetize', 'moduleToWidgetize' => 'FeinwerkCampaignScore', 'actionToWidgetize' => 'getScore']));
    }

    public function test_aScoreOfAnotherReportIsLeftAlone(): void
    {
        $this->assertNull(ScoreUnit::forColumn('score', ['module' => 'FeinwerkTrackingSpamScore', 'action' => 'getScore']));
        $this->assertNull(ScoreUnit::forColumn('score', ['module' => 'API', 'apiModule' => 'Other', 'apiAction' => 'getScore']));
        $this->assertNull(ScoreUnit::forColumn('score', []));
    }

    public function test_otherColumnsOfTheReportAreLeftAlone(): void
    {
        $this->assertNull(ScoreUnit::forColumn('campaign_visits', ['module' => 'FeinwerkCampaignScore', 'action' => 'getScore']));
    }
}
