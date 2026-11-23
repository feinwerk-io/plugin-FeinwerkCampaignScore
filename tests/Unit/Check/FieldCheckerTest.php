<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Check;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Check\FieldChecker;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Finding;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Convention;
use Piwik\Plugins\FeinwerkCampaignScore\Check\MediumList;

/**
 * @group FeinwerkCampaignScore
 */
class FieldCheckerTest extends TestCase
{
    public function test_spellingVariants_becomeOneFindingWithTheMostVisitedCleanValueAsSuggestion(): void
    {
        $findings = FieldChecker::check('name', ['newsletter' => 80, 'sale' => 10, 'Newsletter' => 120, 'news_letter' => 3]);

        $this->assertCount(1, $findings);
        $this->assertSame(Finding::VARIANTS, $findings[0]->type);
        $this->assertSame('name', $findings[0]->field);
        $this->assertSame(['Newsletter' => 120, 'newsletter' => 80, 'news_letter' => 3], $findings[0]->values);
        $this->assertSame(203, $findings[0]->visits);
        $this->assertSame('newsletter', $findings[0]->suggestion);
    }

    public function test_spellingVariants_withoutACleanValue_suggestTheCleanFormOfTheMostVisited(): void
    {
        $findings = FieldChecker::check('name', ['Black Friday' => 5, 'black  friday' => 9]);

        $this->assertCount(1, $findings);
        $this->assertSame('black-friday', $findings[0]->suggestion);
    }

    public function test_aSingleValueThatIsNotClean_isAFormatFinding(): void
    {
        $findings = FieldChecker::check('source', ['Black Friday 2026' => 7, 'google' => 50]);

        $this->assertCount(1, $findings);
        $this->assertSame(Finding::FORMAT, $findings[0]->type);
        $this->assertSame(['Black Friday 2026' => 7], $findings[0]->values);
        $this->assertSame(7, $findings[0]->visits);
        $this->assertSame('black-friday-2026', $findings[0]->suggestion);
    }

    public function test_mediums_outsideTheList_areUnusualWithoutSuggestion(): void
    {
        $findings = FieldChecker::check('medium', ['cpc' => 10, 'Facebook Ads' => 4, 'instagram' => 2]);

        $this->assertCount(2, $findings);
        $this->assertSame([Finding::UNUSUAL_MEDIUM, Finding::UNUSUAL_MEDIUM], [$findings[0]->type, $findings[1]->type]);
        $this->assertSame(['Facebook Ads' => 4], $findings[0]->values);
        $this->assertSame(['instagram' => 2], $findings[1]->values);
        $this->assertNull($findings[0]->suggestion);
    }

    public function test_aMediumWhoseCleanFormIsInTheList_isAFormatFinding(): void
    {
        $findings = FieldChecker::check('medium', ['Email' => 3]);

        $this->assertCount(1, $findings);
        $this->assertSame(Finding::FORMAT, $findings[0]->type);
        $this->assertSame('email', $findings[0]->suggestion);
    }

    public function test_variantsOnly_skipsTheFormatCheck(): void
    {
        $findings = FieldChecker::check('keyword', ['Blue Shoes' => 3, 'blue-shoes' => 2, 'red shoes' => 1]);

        $this->assertCount(1, $findings);
        $this->assertSame(Finding::VARIANTS, $findings[0]->type);
        $this->assertSame(['Blue Shoes' => 3, 'blue-shoes' => 2], $findings[0]->values);
    }

    public function test_numericValuesAndEmptyValues_areHandled(): void
    {
        $this->assertSame([], FieldChecker::check('name', [2026 => 4, '' => 9]));
    }

    public function test_findings_areSortedByVisits(): void
    {
        $findings = FieldChecker::check('name', ['A' => 1, 'B b' => 5, 'c' => 2, 'C' => 2]);

        $this->assertSame([5, 4, 1], array_map(static function (Finding $f): int {
            return $f->visits;
        }, $findings));
    }
}
