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
use Piwik\Plugins\FeinwerkCampaignScore\Check\FieldChecker;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Finding;
use Piwik\Plugins\FeinwerkCampaignScore\Check\MediumList;

/**
 * The rules added after 5.0.0: accepted values, separator, name pattern, source aliases, typo hints, more fields.
 *
 * @group FeinwerkCampaignScore
 */
class FieldRulesTest extends TestCase
{
    /**
     * @return list<string>
     */
    private static function summary(array $findings): array
    {
        return array_map(static function (Finding $f): string {
            return $f->type . ' ' . implode('|', array_map('strval', array_keys($f->values))) . ' > ' . (string) $f->suggestion;
        }, $findings);
    }

    private static function convention(array $accepted = [], string $separator = Convention::SEPARATOR_ANY, ?string $pattern = null): Convention
    {
        return new Convention(MediumList::defaults(), array_fill_keys($accepted, true), $separator, $pattern);
    }

    public function test_acceptedValues_produceNoFindingAndLeaveTheirGroup(): void
    {
        $findings = FieldChecker::check('medium', ['email' => 50, 'e-mail' => 20, 'Facebook Ads' => 4], self::convention(['e-mail', 'Facebook Ads']));

        $this->assertSame([], $findings);
    }

    public function test_separator_aValueWithTheOtherSeparatorIsAFinding(): void
    {
        $findings = FieldChecker::check('name', ['black_friday' => 5, 'spring-sale' => 9], self::convention([], Convention::SEPARATOR_HYPHEN));

        $this->assertSame(['separator black_friday > black-friday'], self::summary($findings));
    }

    public function test_separator_mixedSeparatorsAreAFindingEvenWithoutARule(): void
    {
        $this->assertSame(['separator black-friday_2026 > black-friday-2026'], self::summary(FieldChecker::check('name', ['black-friday_2026' => 3])));
    }

    public function test_pattern_aCleanNameOutsideThePatternIsAFinding(): void
    {
        $findings = FieldChecker::check('name', ['2026-spring-sale' => 9, 'spring-sale' => 4], self::convention([], Convention::SEPARATOR_ANY, '^[0-9]{4}-'));

        $this->assertSame(['pattern spring-sale > '], self::summary($findings));
    }

    public function test_pattern_onlyAppliesToCampaignNames(): void
    {
        $this->assertSame([], FieldChecker::check('source', ['google' => 9], self::convention([], Convention::SEPARATOR_ANY, '^[0-9]{4}-')));
    }

    public function test_sourceAliases_groupWithTheCanonicalNameAsSuggestion(): void
    {
        $findings = FieldChecker::check('source', ['fb' => 30, 'facebook' => 10, 'l.facebook.com' => 2]);

        $this->assertSame(['variants fb|facebook|l.facebook.com > facebook'], self::summary($findings));
    }

    public function test_sourceAliases_aLoneAliasIsAnAliasFinding(): void
    {
        $this->assertSame(['alias fb > facebook'], self::summary(FieldChecker::check('source', ['fb' => 30])));
    }

    public function test_sourceAliases_canBeSwitchedOff(): void
    {
        $convention = new Convention(MediumList::defaults(), [], Convention::SEPARATOR_ANY, null, false);

        $this->assertSame([], FieldChecker::check('source', ['fb' => 30, 'facebook' => 3], $convention));
    }

    public function test_typoHints_pairSimilarValuesWithTheMoreVisitedFirst(): void
    {
        $findings = FieldChecker::check('name', ['newsletter-november' => 80, 'newsleter-november' => 3, 'winter-sale' => 40]);

        $this->assertSame(['similar newsletter-november|newsleter-november > newsletter-november'], self::summary($findings));
    }

    public function test_typoHints_ignoreNumbersShortValuesAndCanBeSwitchedOff(): void
    {
        $this->assertSame([], FieldChecker::check('name', ['sale-2025' => 5, 'sale-2026' => 9, 'q3-promo' => 2, 'q4-promo' => 2]));
        $this->assertSame([], FieldChecker::check('name', ['spring' => 5, 'sprint' => 9]), 'shorter than 8 characters');
        $off = new Convention(MediumList::defaults(), [], Convention::SEPARATOR_ANY, null, true, false);
        $this->assertSame([], FieldChecker::check('name', ['newsletter-november' => 80, 'newsleter-november' => 3], $off));
    }

    public function test_moreFields_contentGetsVariantsOnlyGroupAndPlacementAlsoTheFormat(): void
    {
        $this->assertSame([], FieldChecker::check('content', ['Banner 300x250' => 3]));
        $this->assertSame(['variants Banner A|banner-a > banner-a'], self::summary(FieldChecker::check('content', ['Banner A' => 3, 'banner-a' => 1])));
        $this->assertSame(['format Winter Group > winter-group'], self::summary(FieldChecker::check('group', ['Winter Group' => 3])));
        $this->assertSame(['format Top Banner > top-banner'], self::summary(FieldChecker::check('placement', ['Top Banner' => 3])));
    }

    public function test_variantSuggestion_prefersAValueThatFollowsTheSeparatorRule(): void
    {
        $findings = FieldChecker::check('name', ['news_letter' => 50, 'news-letter' => 10], self::convention([], Convention::SEPARATOR_HYPHEN));

        $this->assertSame(['variants news_letter|news-letter > news-letter'], self::summary($findings));
    }

    public function test_isCleanValue_judgesASingleValueWithAllRules(): void
    {
        $convention = self::convention(['Partner Mail'], Convention::SEPARATOR_HYPHEN, '^[0-9]{4}-');

        $this->assertTrue(FieldChecker::isCleanValue('name', '2026-sale', $convention));
        $this->assertFalse(FieldChecker::isCleanValue('name', 'sale', $convention), 'pattern');
        $this->assertFalse(FieldChecker::isCleanValue('name', '2026_sale', $convention), 'separator');
        $this->assertTrue(FieldChecker::isCleanValue('medium', 'Partner Mail', $convention), 'accepted');
        $this->assertFalse(FieldChecker::isCleanValue('medium', 'instagram', $convention), 'not in the list');
        $this->assertFalse(FieldChecker::isCleanValue('source', 'fb', $convention), 'alias');
        $this->assertTrue(FieldChecker::isCleanValue('source', 'facebook', $convention));
    }

    /** Review 2026-11-13: a value with a scored finding is not also the rare half of a typo pair. */
    public function test_typoHints_skipValuesThatAlreadyHaveAFinding(): void
    {
        $findings = FieldChecker::check('name', ['sommer-aktion-2026' => 80, 'Sommer-Aktoin-2026' => 3]);

        $this->assertSame(['format Sommer-Aktoin-2026 > sommer-aktoin-2026'], self::summary($findings));
    }

    /** Review 2026-11-13: an umlaut is one character, not two bytes. */
    public function test_typoHints_countCharactersNotBytes(): void
    {
        $convention = new Convention(MediumList::defaults(), ['frühlingsaktion' => true, 'fruhlingsaktion' => true]);
        $findings = FieldChecker::check('content', ['frühlingsaktion' => 9, 'fruhlingsaktion' => 2], $convention);
        $this->assertSame([], $findings, 'accepted values give no hint');

        $this->assertSame(1, FieldChecker::distance('frühling', 'fruhling'));
        $this->assertSame(3, FieldChecker::distance('äöü', 'aou'));
    }
}
