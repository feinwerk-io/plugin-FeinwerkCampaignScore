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
 * Accepted values in the score, context per finding, "new" findings and the previous score.
 *
 * @group FeinwerkCampaignScore
 */
class AnalyzerContextTest extends TestCase
{
    private static function unit(?string $name, int $visits, ?string $source, ?string $medium): array
    {
        return ['name' => $name, 'source' => $source, 'medium' => $medium, 'visits' => $visits];
    }

    private static function data(): CampaignData
    {
        return new CampaignData(
            100,
            true,
            [
                'name' => ['winter-sale' => 70, 'spring-sale' => 30],
                'keyword' => [],
                'source' => ['facebook' => 60, 'google' => 40],
                'medium' => ['social' => 40, 'Facebook Ads' => 20, 'cpc' => 40],
            ],
            [],
            [
                self::unit('winter-sale', 40, 'facebook', 'social'),
                self::unit('winter-sale', 15, 'facebook', 'Facebook Ads'),
                self::unit('spring-sale', 5, 'facebook', 'Facebook Ads'),
                self::unit('winter-sale', 15, 'google', 'cpc'),
                self::unit('spring-sale', 25, 'google', 'cpc'),
            ],
            0
        );
    }

    private static function finding(array $findings, string $type, string $field): Finding
    {
        foreach ($findings as $finding) {
            if ($finding->type === $type && $finding->field === $field) {
                return $finding;
            }
        }
        self::fail("no $type finding for $field");
    }

    public function test_contexts_nameTheCampaignsAndSourcesOfAFindingMostVisitedFirst(): void
    {
        $result = Analyzer::analyze(self::data(), Convention::defaults());

        $this->assertSame([
            ['name' => 'winter-sale', 'source' => 'facebook', 'medium' => 'Facebook Ads', 'visits' => 15],
            ['name' => 'spring-sale', 'source' => 'facebook', 'medium' => 'Facebook Ads', 'visits' => 5],
        ], self::finding($result->findings, Finding::UNUSUAL_MEDIUM, 'medium')->contexts);
    }

    public function test_acceptedValues_countAsCleanInTheScore(): void
    {
        $plain = Analyzer::analyze(self::data(), Convention::defaults());
        $accepted = Analyzer::analyze(self::data(), new Convention(MediumList::defaults(), ['Facebook Ads' => true]));

        $this->assertSame(80, $plain->cleanVisits);
        $this->assertSame(100, $accepted->cleanVisits);
        $this->assertSame([], $accepted->findings);
    }

    public function test_namePattern_makesNamesOutsideItUncleanInTheScore(): void
    {
        $result = Analyzer::analyze(self::data(), new Convention(MediumList::defaults(), ['Facebook Ads' => true], Convention::SEPARATOR_ANY, '^winter-'));

        $this->assertSame(70, $result->cleanVisits, 'spring-sale is outside the pattern');
    }

    public function test_newFindings_areMarkedAgainstThePreviousPeriodAndItsScoreIsKept(): void
    {
        $previousData = new CampaignData(
            10,
            true,
            ['name' => ['winter-sale' => 10], 'keyword' => [], 'source' => ['facebook' => 10], 'medium' => ['social' => 10]],
            [],
            [self::unit('winter-sale', 10, 'facebook', 'social')],
            0
        );
        $previous = Analyzer::analyze($previousData, Convention::defaults());

        $result = Analyzer::analyze(self::data(), Convention::defaults(), $previous);

        $this->assertTrue(self::finding($result->findings, Finding::UNUSUAL_MEDIUM, 'medium')->isNew);
        $this->assertEqualsWithDelta(1.0, $result->previousScore, 0.0001);

        $again = Analyzer::analyze(self::data(), Convention::defaults(), $result);
        $this->assertFalse(self::finding($again->findings, Finding::UNUSUAL_MEDIUM, 'medium')->isNew);
    }

    public function test_withoutAPreviousPeriod_nothingIsNewAndThereIsNoPreviousScore(): void
    {
        $result = Analyzer::analyze(self::data(), Convention::defaults());

        $this->assertNull($result->previousScore);
        $this->assertFalse(self::finding($result->findings, Finding::UNUSUAL_MEDIUM, 'medium')->isNew);
    }

    public function test_typoHints_doNotLowerTheScore(): void
    {
        $data = new CampaignData(
            90,
            false,
            ['name' => ['newsletter-november' => 80, 'newsleter-november' => 10], 'keyword' => []],
            [],
            [self::unit('newsletter-november', 80, null, null), self::unit('newsleter-november', 10, null, null)],
            0
        );

        $result = Analyzer::analyze($data, Convention::defaults());

        $this->assertSame(90, $result->cleanVisits);
        $this->assertSame([Finding::SIMILAR], array_map(static function (Finding $f): string {
            return $f->type;
        }, $result->findings));
    }

    public function test_moreFields_withMarketingCampaignsReporting_areCheckedButNotScored(): void
    {
        $data = new CampaignData(
            10,
            true,
            ['name' => ['a' => 10], 'keyword' => [], 'source' => ['google' => 10], 'medium' => ['cpc' => 10], 'group' => ['Winter Group' => 10]],
            [],
            [self::unit('a', 10, 'google', 'cpc')],
            0
        );

        $result = Analyzer::analyze($data, Convention::defaults());

        $this->assertSame(10, $result->cleanVisits);
        $this->assertSame(Finding::FORMAT, self::finding($result->findings, Finding::FORMAT, 'group')->type);
    }

    /** Review 2026-11-13: an empty previous period is no comparison (otherwise every finding would be "new"). */
    public function test_anEmptyPreviousPeriod_marksNothingNew(): void
    {
        $empty = Analyzer::analyze(new CampaignData(0, true, ['name' => [], 'keyword' => [], 'source' => [], 'medium' => []], [], [], 0), Convention::defaults());

        $result = Analyzer::analyze(self::data(), Convention::defaults(), $empty);

        $this->assertFalse(self::finding($result->findings, Finding::UNUSUAL_MEDIUM, 'medium')->isNew);
        $this->assertNull($result->previousScore);
    }

    /** Review 2026-11-13: two aliases with the same canonical name are different findings for "new". */
    public function test_newKey_tellsFindingsWithTheSameSuggestionApart(): void
    {
        $fb = new Finding(Finding::ALIAS, 'source', ['fb' => 3], 3, 'facebook');
        $fbCom = new Finding(Finding::ALIAS, 'source', ['facebook.com' => 3], 3, 'facebook');
        $variantsA = new Finding(Finding::VARIANTS, 'name', ['newsletter' => 5, 'Newsletter' => 2], 7, 'newsletter');
        $variantsB = new Finding(Finding::VARIANTS, 'name', ['newsletter' => 5, 'news_letter' => 1], 6, 'newsletter');

        $this->assertNotSame($fb->key(), $fbCom->key());
        $this->assertSame($variantsA->key(), $variantsB->key(), 'a variant group stays the same finding when its spellings change');
    }
}
