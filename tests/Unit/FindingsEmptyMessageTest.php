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

    /** D5 (Peter, 2026-11-24): the visible "Accept" of rows without a suggestion, its texts escaped. */
    public function test_the_accept_button_is_a_plain_escaped_button(): void
    {
        $button = GetFindings::acceptButton('Accept <value>', 'Count "this" as clean');
        $this->assertStringStartsWith('<button type="button" class="fwcs-accept-btn fwcs-row-btn"', $button);
        $this->assertStringStartsWith('<button type="button" class="fwcs-medium-btn fwcs-row-btn"', GetFindings::acceptButton('Add', 'Usual', '', 'fwcs-medium-btn'));
        $this->assertStringContainsString('title="Count &quot;this&quot; as clean"', $button);
        $this->assertStringContainsString('>Accept &lt;value&gt;</button>', $button);
        $this->assertStringNotContainsString('aria-label', $button);

        // a name per row for screen readers, escaped like the rest
        $named = GetFindings::acceptButton('Accept value', 'Count as clean', 'Accept value: <b>fb</b> & "x"');
        $this->assertStringContainsString('aria-label="Accept value: &lt;b&gt;fb&lt;/b&gt; &amp; &quot;x&quot;"', $named);
    }
}
