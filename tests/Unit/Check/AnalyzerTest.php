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
use Piwik\Plugins\FeinwerkCampaignScore\Check\MediumList;

/**
 * @group FeinwerkCampaignScore
 */
class AnalyzerTest extends TestCase
{
    private static function unit(?string $name, int $visits, ?string $source = null, ?string $medium = null): array
    {
        return ['name' => $name, 'source' => $source, 'medium' => $medium, 'visits' => $visits];
    }

    /**
     * @return list<string>
     */
    private static function summary(array $findings): array
    {
        return array_map(static function (Finding $f): string {
            return $f->type . ' ' . $f->field . ' ' . implode('|', array_keys($f->values)) . ' ' . $f->visits;
        }, $findings);
    }

    public function test_withoutSourceAndMedium_scoresTheCampaignNames(): void
    {
        $data = new CampaignData(
            100,
            false,
            ['name' => ['spring-sale' => 60, 'Black Friday' => 20, 'newsletter' => 10, 'news_letter' => 5], 'keyword' => []],
            ['name' => 5],
            [
                self::unit('spring-sale', 60),
                self::unit('Black Friday', 20),
                self::unit('newsletter', 10),
                self::unit('news_letter', 5),
            ],
            5
        );

        $result = Analyzer::analyze($data, Convention::defaults());

        // the variant news_letter counts as not clean: only the suggested spelling of a group is clean
        $this->assertSame(70, $result->cleanVisits);
        $this->assertSame(95, $result->checkedVisits);
        $this->assertSame(5, $result->uncheckedVisits);
        $this->assertSame(100, $result->campaignVisits);
        $this->assertEqualsWithDelta(70 / 95, $result->score, 0.0001);
        $this->assertFalse($result->withSourceMedium);
        $this->assertSame([
            'format name Black Friday 20',
            'variants name newsletter|news_letter 15',
        ], self::summary($result->findings));
    }

    public function test_withSourceAndMedium_checksAllThreeFieldsAndFindsMissingValues(): void
    {
        $data = new CampaignData(
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

        $result = Analyzer::analyze($data, Convention::defaults());

        // 50 visits have neither source nor medium: they are in no unit, but in the basis
        $this->assertSame(100, $result->cleanVisits);
        $this->assertSame(200, $result->checkedVisits);
        $this->assertEqualsWithDelta(0.5, $result->score, 0.0001);
        $this->assertSame([
            'incomplete medium  60',
            'incomplete source  50',
            'format medium Email 40',
        ], self::summary($result->findings));
    }

    public function test_aMediumOutsideTheList_makesTheVisitsUnclean(): void
    {
        $data = new CampaignData(
            10,
            true,
            ['name' => ['a' => 10], 'keyword' => [], 'source' => ['facebook' => 10], 'medium' => ['instagram' => 10]],
            [],
            [self::unit('a', 10, 'facebook', 'instagram')],
            0
        );

        $result = Analyzer::analyze($data, new Convention(MediumList::fromText("instagram\ncpc")));
        $this->assertSame(10, $result->cleanVisits);
        $this->assertSame([], $result->findings);

        $result = Analyzer::analyze($data, Convention::defaults());
        $this->assertSame(0, $result->cleanVisits);
        $this->assertSame(['unusual_medium medium instagram 10'], self::summary($result->findings));
    }

    public function test_othersRowsOfAField_countAsHavingAValue(): void
    {
        $data = new CampaignData(
            30,
            true,
            ['name' => ['a' => 20], 'keyword' => [], 'source' => ['google' => 30], 'medium' => ['cpc' => 30]],
            ['name' => 10],
            [self::unit('a', 20, 'google', 'cpc')],
            10
        );

        $result = Analyzer::analyze($data, Convention::defaults());

        $this->assertSame([], $result->findings);
        $this->assertSame(20, $result->checkedVisits);
        $this->assertEqualsWithDelta(1.0, $result->score, 0.0001);
    }

    /**
     * Visits with no value at all in Marketing Campaigns Reporting (tracked before it was activated) are no missing
     * values: they cannot be checked.
     */
    public function test_visitsWithoutAnyKnownValue_cannotBeCheckedAndAreNoMissingValues(): void
    {
        $data = new CampaignData(
            130,
            true,
            ['name' => ['a' => 30], 'keyword' => [], 'source' => ['google' => 30], 'medium' => ['cpc' => 25]],
            [],
            [self::unit('a', 25, 'google', 'cpc'), self::unit('a', 5, null, null)],
            0,
            100
        );

        $result = Analyzer::analyze($data, Convention::defaults());

        $this->assertSame(['incomplete medium  5'], self::summary($result->findings));
        $this->assertSame(30, $result->checkedVisits);
        $this->assertSame(100, $result->uncheckedVisits);
        $this->assertSame(130, $result->campaignVisits);
        $this->assertEqualsWithDelta(25 / 30, $result->score, 0.0001);
    }

    public function test_keywordVariants_areFindingsButNotPartOfTheScore(): void
    {
        $data = new CampaignData(
            10,
            false,
            ['name' => ['a' => 10], 'keyword' => ['Blue Shoes' => 6, 'blue shoes' => 4, 'red shoes' => 1]],
            [],
            [self::unit('a', 10)],
            0
        );

        $result = Analyzer::analyze($data, Convention::defaults());

        $this->assertEqualsWithDelta(1.0, $result->score, 0.0001);
        $this->assertSame(['variants keyword Blue Shoes|blue shoes 10'], self::summary($result->findings));
    }

    public function test_score_isNullWithoutCampaignVisitsOrWithoutCheckableVisits(): void
    {
        $empty = new CampaignData(0, false, ['name' => [], 'keyword' => []], [], [], 0);
        $this->assertNull(Analyzer::analyze($empty, Convention::defaults())->score);

        $allOthers = new CampaignData(5, false, ['name' => [], 'keyword' => []], ['name' => 5], [], 5);
        $result = Analyzer::analyze($allOthers, Convention::defaults());
        $this->assertNull($result->score);
        $this->assertSame(0, $result->checkedVisits);
    }

    public function test_cleanVisits_neverExceedTheBasis(): void
    {
        $data = new CampaignData(10, false, ['name' => ['a' => 12], 'keyword' => []], [], [self::unit('a', 12)], 0);

        $result = Analyzer::analyze($data, Convention::defaults());

        $this->assertSame(10, $result->cleanVisits);
        $this->assertEqualsWithDelta(1.0, $result->score, 0.0001);
    }
}
