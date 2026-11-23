<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Finding;
use Piwik\Plugins\FeinwerkCampaignScore\Data\SegmentBuilder;

/**
 * The segment of a finding: the visits with the values to fix, for Matomo's visitor log.
 *
 * @group FeinwerkCampaignScore
 */
class SegmentBuilderTest extends TestCase
{
    public function test_withMarketingCampaignsReporting_theCampaignDimensionOfTheField(): void
    {
        $finding = new Finding(Finding::UNUSUAL_MEDIUM, 'medium', ['Facebook Ads' => 4], 4, null);

        $this->assertSame('referrerType==campaign;campaignMedium==Facebook+Ads', SegmentBuilder::forFinding($finding, true));
    }

    public function test_variants_onlyTheSpellingsToReplaceJoinedWithOr(): void
    {
        $finding = new Finding(Finding::VARIANTS, 'name', ['newsletter' => 80, 'Newsletter' => 30, 'news_letter' => 3], 113, 'newsletter');

        $this->assertSame(
            'referrerType==campaign;campaignName==Newsletter,campaignName==news_letter',
            SegmentBuilder::forFinding($finding, true)
        );
    }

    public function test_withoutMarketingCampaignsReporting_matomosReferrerDimensions(): void
    {
        $this->assertSame(
            'referrerType==campaign;referrerName==black+friday',
            SegmentBuilder::forFinding(new Finding(Finding::FORMAT, 'name', ['black friday' => 4], 4, 'black-friday'), false)
        );
        $this->assertSame(
            'referrerType==campaign;referrerKeyword==Blue+Shoes',
            SegmentBuilder::forFinding(new Finding(Finding::VARIANTS, 'keyword', ['blue shoes' => 5, 'Blue Shoes' => 2], 7, 'blue shoes'), false)
        );
        $this->assertNull(SegmentBuilder::forFinding(new Finding(Finding::FORMAT, 'group', ['A B' => 1], 1, 'a-b'), false));
    }

    public function test_missingValues_matchTheEmptyValue(): void
    {
        $this->assertSame(
            'referrerType==campaign;campaignSource==',
            SegmentBuilder::forFinding(new Finding(Finding::INCOMPLETE, 'source', [], 9, null), true)
        );
    }

    public function test_specialCharacters_areUrlEncodedLikeMatomosOwnSegments(): void
    {
        $finding = new Finding(Finding::FORMAT, 'name', ['a,b;c==d&amp;e' => 1], 1, null);

        $this->assertSame('referrerType==campaign;campaignName==a%2Cb%3Bc%3D%3Dd%26amp%3Be', SegmentBuilder::forFinding($finding, true));
    }

    public function test_longOrLists_areCut(): void
    {
        $values = [];
        for ($i = 0; $i < 30; $i++) {
            $values['Value' . $i] = 30 - $i;
        }
        $segment = (string) SegmentBuilder::forFinding(new Finding(Finding::VARIANTS, 'name', $values, 1, 'value0'), true);

        $this->assertSame(SegmentBuilder::MAX_VALUES, substr_count($segment, 'campaignName=='));
    }
}
