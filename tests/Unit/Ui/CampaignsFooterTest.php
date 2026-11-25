<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Ui;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\CampaignsFooter;

/** The line with the score under Matomo's campaign names report (Peter, 2026-11-24: "D4 ja"). */
class CampaignsFooterTest extends TestCase
{
    /** @param array<int, string> $args */
    public static function translate(string $key, array $args = []): string
    {
        return $key . ($args === [] ? '' : '(' . implode('|', $args) . ')');
    }

    private static function html(?array $score, string $url = 'index.php?a=1&b=2'): string
    {
        return CampaignsFooter::html($score, $url, [self::class, 'translate'], static function ($p): string {
            return $p . '%';
        }, static function ($n): string {
            return (string) $n;
        });
    }

    public function test_only_for_the_report_table_of_a_single_period(): void
    {
        $this->assertTrue(CampaignsFooter::isFor(false, false));
        $this->assertFalse(CampaignsFooter::isFor(true, false), 'not in the keyword subtables');
        $this->assertFalse(CampaignsFooter::isFor(false, true), 'not for several periods');
    }

    public function test_the_score_with_its_findings_and_an_escaped_link(): void
    {
        $this->assertSame(
            '<div class="fwcs-campaigns-footer">FeinwerkCampaignScore_CampaignsFooter(88%|10) '
            . '<a href="index.php?a=1&amp;b=2">FeinwerkCampaignScore_CampaignsFooterLink</a></div>',
            self::html(['score' => 87.6, 'findings' => 10])
        );
    }

    public function test_one_finding_and_no_findings_without_a_link(): void
    {
        $this->assertSame(
            '<div class="fwcs-campaigns-footer">FeinwerkCampaignScore_CampaignsFooterOne(97%) '
            . '<a href="index.php?a=1&amp;b=2">FeinwerkCampaignScore_CampaignsFooterLink</a></div>',
            self::html(['score' => 97.2, 'findings' => 1])
        );
        $this->assertSame(
            '<div class="fwcs-campaigns-footer">FeinwerkCampaignScore_CampaignsFooterNone(100%)</div>',
            self::html(['score' => 100, 'findings' => 0]),
            'nothing to show: no link'
        );
    }

    public function test_nothing_without_a_score(): void
    {
        $this->assertSame('', self::html(null));
        $this->assertSame('', self::html(['campaign_visits' => 0, 'findings' => 0]), 'no checkable campaign visits');
    }

    public function test_the_report_link_keeps_the_segment(): void
    {
        $url = \Piwik\Plugins\FeinwerkCampaignScore\Ui\ScoreWidget::reportUrl(3, 'day', '2026-11-20', 'referrerName%3D%3Dgoogle');
        [$query, $hash] = explode('#?', $url, 2);
        $this->assertStringEndsWith('&segment=referrerName%3D%3Dgoogle', $query);
        $this->assertStringEndsWith('&segment=referrerName%3D%3Dgoogle', $hash);
        $this->assertStringContainsString('subcategory=FeinwerkCampaignScore_CampaignScore', $hash);

        $this->assertStringNotContainsString('segment=', \Piwik\Plugins\FeinwerkCampaignScore\Ui\ScoreWidget::reportUrl(3, 'day', '2026-11-20'));
    }
}
