<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Check;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Convention;
use Piwik\Plugins\FeinwerkCampaignScore\Check\MediumList;
use Piwik\Plugins\FeinwerkCampaignScore\Check\SourceAliases;
use Piwik\Plugins\FeinwerkCampaignScore\Check\ValueRules;

/**
 * @group FeinwerkCampaignScore
 */
class ConventionTest extends TestCase
{
    public function test_defaults_acceptNothingAllowEitherSeparatorAndHaveNoPattern(): void
    {
        $convention = Convention::defaults();

        $this->assertSame(MediumList::DEFAULTS, $convention->mediums->values());
        $this->assertFalse($convention->isAccepted('e-mail'));
        $this->assertSame(Convention::SEPARATOR_ANY, $convention->separator);
        $this->assertTrue($convention->matchesNamePattern('Anything at all'));
        $this->assertTrue($convention->sourceAliases);
        $this->assertTrue($convention->typoHints);
    }

    public function test_acceptedValues_areReadOnePerLineAndMatchExactly(): void
    {
        $convention = new Convention(MediumList::defaults(), Convention::acceptedFromText("e-mail\n  Black Friday 2026 \r\n\n"));

        $this->assertTrue($convention->isAccepted('e-mail'));
        $this->assertTrue($convention->isAccepted('Black Friday 2026'));
        $this->assertFalse($convention->isAccepted('E-Mail'));
    }

    public function test_separatorRule_findsTheWrongOrMixedSeparators(): void
    {
        $any = Convention::defaults();
        $hyphen = new Convention(MediumList::defaults(), [], Convention::SEPARATOR_HYPHEN);
        $underscore = new Convention(MediumList::defaults(), [], Convention::SEPARATOR_UNDERSCORE);

        $this->assertNull($any->separatorFix('black-friday'));
        $this->assertNull($any->separatorFix('black_friday'));
        $this->assertSame('black-friday-2026', $any->separatorFix('black-friday_2026'), 'mixed: the more frequent one wins, hyphen on a tie');
        $this->assertSame('a_b_c_d', $any->separatorFix('a_b_c-d'));
        $this->assertSame('black-friday', $hyphen->separatorFix('black_friday'));
        $this->assertNull($hyphen->separatorFix('black-friday'));
        $this->assertSame('black_friday', $underscore->separatorFix('black-friday'));
    }

    public function test_namePattern_isAnchoredByTheAdminAndAnInvalidPatternIsIgnored(): void
    {
        $convention = new Convention(MediumList::defaults(), [], Convention::SEPARATOR_ANY, '^[0-9]{4}-[a-z0-9-]+$');

        $this->assertTrue($convention->matchesNamePattern('2026-black-friday'));
        $this->assertFalse($convention->matchesNamePattern('black-friday'));
        $this->assertTrue(Convention::isValidPattern('^[0-9]{4}-'));
        $this->assertFalse(Convention::isValidPattern('([a-z'));
        $this->assertTrue((new Convention(MediumList::defaults(), [], Convention::SEPARATOR_ANY, '([a-z'))->matchesNamePattern('x'));
    }

    public function test_cleaned_followsTheSeparatorOfTheConvention(): void
    {
        $this->assertSame('black-friday-2026', ValueRules::cleaned('Black Friday_2026', Convention::SEPARATOR_HYPHEN));
        $this->assertSame('black_friday_2026', ValueRules::cleaned('Black Friday-2026', Convention::SEPARATOR_UNDERSCORE));
        $this->assertSame('black-friday_2026', ValueRules::cleaned('Black Friday_2026'), 'any: kept as written');
    }

    public function test_sourceAliases_knowTheCommonSpellingsOfLargePlatforms(): void
    {
        $this->assertSame('facebook', SourceAliases::canonical('fb'));
        $this->assertSame('facebook', SourceAliases::canonical('m.facebook.com'));
        $this->assertSame('facebook', SourceAliases::canonical('Facebook'));
        $this->assertSame('google', SourceAliases::canonical('Google Ads'));
        $this->assertSame('instagram', SourceAliases::canonical('IG'));
        $this->assertNull(SourceAliases::canonical('newsletter'));
        foreach (SourceAliases::MAP as $canonical) {
            $this->assertTrue(ValueRules::isClean($canonical), $canonical);
        }
    }

    /** Review 2026-11-13: an escaped tilde in the pattern stays an escaped tilde. */
    public function test_namePattern_withATilde(): void
    {
        $convention = new Convention(MediumList::defaults(), [], Convention::SEPARATOR_ANY, '^a\\~b$');

        $this->assertTrue(Convention::isValidPattern('^a\\~b$'));
        $this->assertTrue($convention->matchesNamePattern('a~b'));
        $this->assertFalse($convention->matchesNamePattern('ab'));
        $this->assertTrue((new Convention(MediumList::defaults(), [], Convention::SEPARATOR_ANY, '^a~b$'))->matchesNamePattern('a~b'));
    }
}
