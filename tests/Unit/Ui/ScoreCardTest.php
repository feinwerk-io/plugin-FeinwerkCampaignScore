<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Ui;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\ScoreCard;

/**
 * What the score card and the dashboard widget show. Pure: translation and number formatting are passed in.
 *
 * @group FeinwerkCampaignScore
 */
class ScoreCardTest extends TestCase
{
    private static function card(array $score, array $findings = [], bool $mcrActivated = true): array
    {
        return ScoreCard::variables(
            $score,
            $findings,
            $mcrActivated,
            static function (string $key, ...$args): string {
                return '[' . $key . ($args ? ':' . implode('|', $args) : '') . ']';
            },
            static function ($number): string {
                return 'n' . $number;
            },
            static function ($percent): string {
                return 'p' . $percent;
            }
        );
    }

    private static function score(array $columns = []): array
    {
        return $columns + [
            'score' => 81.3,
            'clean_visits' => 650,
            'checked_visits' => 800,
            'campaign_visits' => 850,
            'unchecked_visits' => 0,
            'with_source_medium' => 1,
            'findings' => 2,
        ];
    }

    public function test_showsTheRoundedScoreWithItsBasisAndTheCheckedFields(): void
    {
        $card = self::card(self::score());

        $this->assertTrue($card['hasData']);
        $this->assertSame('p81', $card['score'], 'rounded to a whole percent');
        $this->assertSame('ok', $card['level']);
        $this->assertSame('[FeinwerkCampaignScore_ScoreBasis:n650|n800]', $card['basis']);
        $this->assertSame('[FeinwerkCampaignScore_ScoreFieldsAll]', $card['fields']);
        $this->assertNull($card['unchecked']);
        $this->assertNull($card['hint']);
    }

    public function test_delta_toThePreviousPeriodInWholePoints(): void
    {
        $up = self::card(self::score(['score' => 81.3, 'previous_score' => 75.0]));
        $this->assertSame('[FeinwerkCampaignScore_DeltaChange:+6]', $up['delta']);
        $this->assertSame('up', $up['deltaDirection']);

        $down = self::card(self::score(['score' => 70.0, 'previous_score' => 73.4]));
        $this->assertSame('[FeinwerkCampaignScore_DeltaChange:-3]', $down['delta']);
        $this->assertSame('down', $down['deltaDirection']);

        $same = self::card(self::score(['score' => 80.2, 'previous_score' => 79.9]));
        $this->assertSame('[FeinwerkCampaignScore_DeltaSame]', $same['delta']);

        $this->assertNull(self::card(self::score())['delta'], 'no previous period');
    }

    public function test_sparkline_pointsOnAFixedScaleFrom0To100(): void
    {
        $this->assertSame('0,28 60,14 120,0', ScoreCard::sparkline([0.0, 50.0, 100.0]));
        $this->assertSame('0,14 120,0', ScoreCard::sparkline([null, 50.0, 100.0]), 'periods without a score are left out');
        $this->assertNull(ScoreCard::sparkline([80.0]), 'one point is no line');
        $this->assertNull(ScoreCard::sparkline([]));
    }

    public function test_level_followsTheScore(): void
    {
        $this->assertSame('good', self::card(self::score(['score' => 90.0]))['level']);
        $this->assertSame('ok', self::card(self::score(['score' => 70.0]))['level']);
        $this->assertSame('poor', self::card(self::score(['score' => 69.9]))['level']);
    }

    public function test_namesTheVisitsThatCouldNotBeChecked(): void
    {
        $this->assertSame('[FeinwerkCampaignScore_ScoreUnchecked:n50]', self::card(self::score(['unchecked_visits' => 50]))['unchecked']);
    }

    public function test_withoutMarketingCampaignsReporting_onlyTheNameIsCheckedAndAHintSaysWhy(): void
    {
        $card = self::card(self::score(['with_source_medium' => 0]), [], false);

        $this->assertSame('[FeinwerkCampaignScore_ScoreFieldsName]', $card['fields']);
        $this->assertSame('[FeinwerkCampaignScore_HintSourceMedium]', $card['hint']);
    }

    public function test_noHintWhenMarketingCampaignsReportingIsActiveButHadNoDataInThePeriod(): void
    {
        $this->assertNull(self::card(self::score(['with_source_medium' => 0]), [], true)['hint']);
    }

    public function test_withoutCheckableVisits_saysSo(): void
    {
        $card = self::card(['clean_visits' => 0, 'checked_visits' => 0, 'campaign_visits' => 0, 'unchecked_visits' => 0, 'with_source_medium' => 1, 'findings' => 0]);

        $this->assertFalse($card['hasData']);
        $this->assertSame('[FeinwerkCampaignScore_ScoreNoData]', $card['noData']);
        $this->assertSame('[FeinwerkCampaignScore_ScoreNoDataHint]', $card['noDataHint']);
    }

    /** Regression (release review 2026-11-24): campaign visits none of which could be checked are no "no campaign visits". */
    public function test_withCampaignVisitsButNoneCheckable_saysWhyNotTheParameterAdvice(): void
    {
        $card = self::card(['clean_visits' => 0, 'checked_visits' => 0, 'campaign_visits' => 12, 'unchecked_visits' => 12, 'with_source_medium' => 1, 'findings' => 0]);

        $this->assertFalse($card['hasData']);
        $this->assertSame('[FeinwerkCampaignScore_ScoreNoneChecked]', $card['noData']);
        $this->assertSame('[FeinwerkCampaignScore_ScoreUnchecked:n12]', $card['noDataHint']);
    }

    public function test_problems_areTheGivenFindingsReadable(): void
    {
        $card = self::card(self::score(), [
            ['label' => 'Newsletter, newsletter', 'type' => 'variants', 'field' => 'name', 'nb_visits' => 50, 'values' => ['Newsletter' => 30, 'newsletter' => 20], 'suggestion' => 'newsletter'],
            ['label' => '', 'type' => 'incomplete', 'field' => 'medium', 'nb_visits' => 7, 'values' => []],
        ]);

        // with the suggestion where there is one (design D2 of the UI review 2026-11-24)
        $this->assertSame([
            ['problem' => '[FeinwerkCampaignScore_TypeVariants]', 'field' => '[FeinwerkCampaignScore_FieldName]', 'value' => 'Newsletter (n30), newsletter (n20)', 'visits' => 'n50', 'suggestion' => 'newsletter'],
            ['problem' => '[FeinwerkCampaignScore_TypeIncomplete]', 'field' => '[FeinwerkCampaignScore_FieldMedium]', 'value' => '[FeinwerkCampaignScore_EmptyValue]', 'visits' => 'n7', 'suggestion' => ''],
        ], $card['problems']);
    }
}
