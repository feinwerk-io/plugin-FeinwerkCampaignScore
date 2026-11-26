<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Check;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Analyzer;
use Piwik\Plugins\FeinwerkCampaignScore\Check\CampaignData;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Convention;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Finding;

/**
 * What a fix is worth (score points per finding), where the score is lost (clean share per field) and what the team
 * fixed since the previous period.
 *
 * @group FeinwerkCampaignScore
 */
class AnalyzerValueTest extends TestCase
{
    private static function unit(?string $name, int $visits, ?string $source = null, ?string $medium = null): array
    {
        return ['name' => $name, 'source' => $source, 'medium' => $medium, 'visits' => $visits];
    }

    /** @return array<string, float> "type field" => score gain */
    private static function gains(array $findings): array
    {
        $gains = [];
        foreach ($findings as $finding) {
            $gains[$finding->type . ' ' . $finding->field] = $finding->scoreGain;
        }
        return $gains;
    }

    private static function namesOnly(array $names): CampaignData
    {
        $units = [];
        foreach ($names as $name => $visits) {
            $units[] = self::unit((string) $name, $visits);
        }
        return new CampaignData((int) array_sum($names), false, ['name' => $names, 'keyword' => []], [], $units, 0);
    }

    private static function withSourceMedium(): CampaignData
    {
        return new CampaignData(
            200,
            true,
            [
                'name' => ['spring-sale' => 200],
                'keyword' => [],
                'source' => ['google' => 100, 'newsletter' => 50],
                'medium' => ['cpc' => 100, 'Email' => 40],
            ],
            [],
            [
                self::unit('spring-sale', 100, 'google', 'cpc'),
                self::unit('spring-sale', 40, 'newsletter', 'Email'),
                self::unit('spring-sale', 10, 'newsletter', null),
            ],
            0
        );
    }

    public function test_score_gain_counts_the_visits_the_finding_makes_unclean(): void
    {
        $result = Analyzer::analyze(
            self::namesOnly(['spring-sale' => 60, 'Black Friday' => 20, 'newsletter' => 10, 'news_letter' => 5]),
            Convention::defaults()
        );

        // 95 checked visits: fixing "Black Friday" makes 20 clean, the variant news_letter 5 (newsletter is the suggestion)
        $this->assertSame(['format name' => 21.1, 'variants name' => 5.3], self::gains($result->findings));
    }

    public function test_a_visit_with_two_problems_counts_half_for_each_so_the_points_add_up(): void
    {
        $data = new CampaignData(
            100,
            true,
            ['name' => ['spring-sale' => 60, 'Black Friday' => 40], 'keyword' => [], 'source' => ['google' => 100], 'medium' => ['cpc' => 60, 'Email' => 40]],
            [],
            [self::unit('spring-sale', 60, 'google', 'cpc'), self::unit('Black Friday', 40, 'google', 'Email')],
            0
        );
        $result = Analyzer::analyze($data, Convention::defaults());

        // 40 visits with a wrong name and a wrong medium: 20 for each; together the 40 points the score misses
        $this->assertSame(['format name' => 20.0, 'format medium' => 20.0], self::gains($result->findings));
        $this->assertEqualsWithDelta(100 - $result->score * 100, array_sum(self::gains($result->findings)), 0.01);
    }

    public function test_score_gain_with_source_and_medium_needs_the_other_fields_clean(): void
    {
        $result = Analyzer::analyze(self::withSourceMedium(), Convention::defaults());

        $this->assertSame([
            // 10 visits of a unit with a clean name and source; 50 visits without source and medium are in no unit
            // (their share cannot be told)
            'incomplete medium' => 5.0,
            // no unit lacks the source: those visits are outside the units
            'incomplete source' => 0.0,
            'format medium' => 20.0,
        ], self::gains($result->findings));
    }

    public function test_findings_that_do_not_lower_the_score_gain_nothing(): void
    {
        $data = new CampaignData(
            30,
            false,
            ['name' => ['summer-sale' => 30], 'keyword' => ['Shoes' => 20, 'shoes' => 10]],
            [],
            [self::unit('summer-sale', 30)],
            0
        );
        $result = Analyzer::analyze($data, Convention::defaults());

        $this->assertSame(Finding::VARIANTS, $result->findings[0]->type);
        $this->assertSame('keyword', $result->findings[0]->field);
        $this->assertSame(0.0, $result->findings[0]->scoreGain);
    }

    public function test_field_shares_show_where_the_score_is_lost(): void
    {
        $result = Analyzer::analyze(self::withSourceMedium(), Convention::defaults());

        // missing values count as not clean; the score (0.5) is lost in the medium and partly in the source
        $this->assertSame(['name' => 1.0, 'source' => 0.75, 'medium' => 0.5], $result->fieldShares);

        $names = Analyzer::analyze(self::namesOnly(['spring-sale' => 60, 'Black Friday' => 20]), Convention::defaults());
        $this->assertSame(['name' => 0.75], $names->fieldShares, 'without source and medium only the name');
        $this->assertSame(['name' => null], Analyzer::analyze(self::namesOnly([]), Convention::defaults())->fieldShares);
    }

    public function test_fixed_findings_need_the_clean_spelling_in_use_now(): void
    {
        $convention = Convention::defaults();
        $previous = Analyzer::analyze(
            self::namesOnly(['newsletter' => 10, 'news_letter' => 5, 'Black Friday' => 20, 'summer-sale' => 30]),
            $convention
        );
        // news_letter is gone and newsletter is used: fixed; "Black Friday" is gone, but its campaign did not run:
        // not fixed; summer-sale was clean before
        $now = Analyzer::analyze(self::namesOnly(['newsletter' => 12, 'summer-sale' => 30]), $convention, $previous);
        $this->assertSame(1, $now->fixedFindings);

        $this->assertNull(Analyzer::analyze(self::namesOnly(['newsletter' => 12]), $convention)->fixedFindings, 'no previous period');
        $this->assertSame(0, Analyzer::analyze(self::namesOnly(['news_letter' => 3, 'newsletter' => 9]), $convention, $previous)->fixedFindings);
    }

    public function test_a_filled_missing_value_counts_as_fixed(): void
    {
        $convention = Convention::defaults();
        $previous = Analyzer::analyze(self::withSourceMedium(), $convention);
        $complete = new CampaignData(
            100,
            true,
            ['name' => ['spring-sale' => 100], 'keyword' => [], 'source' => ['google' => 100], 'medium' => ['cpc' => 100]],
            [],
            [self::unit('spring-sale', 100, 'google', 'cpc')],
            0
        );

        // both missing values are filled now; "Email" is gone and its clean spelling "email" is not in use: not fixed
        $this->assertSame(2, Analyzer::analyze($complete, $convention, $previous)->fixedFindings);
    }
}
