<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Reports\GetFindings;

/**
 * The empty findings table says "no problems" only when campaign visits were checked (UI review F4 and release review
 * of 2026-11-24: a period whose campaign visits could not be checked was reported as clean).
 */
class FindingsEmptyMessageTest extends TestCase
{
    public function test_the_empty_table_says_which_empty_it_is(): void
    {
        $this->assertSame('FeinwerkCampaignScore_ScoreNoData', GetFindings::emptyMessageKey(['campaign' => 0, 'checked' => 0]));
        $this->assertSame('FeinwerkCampaignScore_ScoreNoneChecked', GetFindings::emptyMessageKey(['campaign' => 12, 'checked' => 0]));
        $this->assertSame('FeinwerkCampaignScore_NoProblems', GetFindings::emptyMessageKey(['campaign' => 12, 'checked' => 12]));
        $this->assertSame('FeinwerkCampaignScore_NoProblems', GetFindings::emptyMessageKey(null), 'unknown basis: as before');
    }
}
