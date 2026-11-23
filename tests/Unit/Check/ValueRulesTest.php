<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Check;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Check\ValueRules;

/**
 * @group FeinwerkCampaignScore
 */
class ValueRulesTest extends TestCase
{
    public function test_normalize_ignoresCaseSpacesAndSeparators(): void
    {
        $this->assertSame('newsletter', ValueRules::normalize('Newsletter'));
        $this->assertSame('newsletter', ValueRules::normalize('news_letter'));
        $this->assertSame('newsletter', ValueRules::normalize(' News-Letter '));
        $this->assertSame('newsletter', ValueRules::normalize('news.letter'));
        $this->assertSame('newsletter', ValueRules::normalize("news\tletter"));
        $this->assertSame('frühling', ValueRules::normalize('FRÜHLING'));
    }

    public function test_isClean_acceptsLowercaseLettersDigitsAndSeparators(): void
    {
        $this->assertTrue(ValueRules::isClean('black-friday_2026.v2'));
        $this->assertTrue(ValueRules::isClean('cpc'));
    }

    public function test_isClean_rejectsUppercaseSpacesAndOtherCharacters(): void
    {
        $this->assertFalse(ValueRules::isClean('Newsletter'));
        $this->assertFalse(ValueRules::isClean('black friday'));
        $this->assertFalse(ValueRules::isClean(' cpc'));
        $this->assertFalse(ValueRules::isClean('frühling'));
        $this->assertFalse(ValueRules::isClean('sale%20'));
        $this->assertFalse(ValueRules::isClean(''));
        $this->assertFalse(ValueRules::isClean("email\n"), 'a trailing line break is not clean (release review)');
    }

    public function test_cleaned_turnsAValueIntoItsCleanForm(): void
    {
        $this->assertSame('black-friday-2026', ValueRules::cleaned('Black Friday 2026'));
        $this->assertSame('news_letter', ValueRules::cleaned('News_Letter'));
        $this->assertSame('fruehling-sale', ValueRules::cleaned('  Frühling -- Sale!'));
        $this->assertSame('strasse', ValueRules::cleaned('Straße'));
        $this->assertSame('cpc', ValueRules::cleaned('cpc'));
    }

    /** Matomo stores tracked values HTML-encoded ("&" as "&amp;"): the suggestion is built from the decoded value. */
    public function test_cleaned_decodesHtmlEntitiesFirst(): void
    {
        $this->assertSame('summer-sale', ValueRules::cleaned('Summer &amp; Sale'));
        $this->assertSame('it-s-on', ValueRules::cleaned('it&#039;s on'));
    }

    public function test_cleaned_returnsEmptyWhenNothingUsableIsLeft(): void
    {
        $this->assertSame('', ValueRules::cleaned('!!!'));
        $this->assertSame('', ValueRules::cleaned('   '));
    }
}
