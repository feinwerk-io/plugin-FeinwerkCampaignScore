<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Promo;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Data\ConventionExport;
use Piwik\Plugins\FeinwerkCampaignScore\Promo\GuideBanner;

/**
 * The guide to Feinwerk Campaigns beside the score card (Peter, 2026-11-13).
 *
 * @group FeinwerkCampaignScore
 */
class GuideBannerTest extends TestCase
{
    public static function translate(string $key, array $args = []): string
    {
        return '[' . $key . ($args ? ':' . implode(',', $args) : '') . ']';
    }

    public function test_noGuideWhenPromotionsAreNotShownOrTheReportIsEmbedded(): void
    {
        $translate = [self::class, 'translate'];
        $this->assertNull(GuideBanner::variables(false, false, 80.0, 'index.php?x', 'en', $translate));
        $this->assertNull(GuideBanner::variables(true, true, 80.0, 'index.php?x', 'en', $translate));
    }

    public function test_theMissingPointsWithThreeStepsWhenThereIsAConventionToExport(): void
    {
        $guide = GuideBanner::variables(true, false, 82.6, 'index.php?module=FeinwerkCampaignScore&action=downloadConvention', 'de', [self::class, 'translate']);

        $this->assertSame('campaigns-guide', $guide['id']);
        // one x pauses the whole place for 30 days; each banner stays away 90 days, so the next one shows after the pause
        $this->assertSame([90, 30], [$guide['days'], $guide['pauseDays']]);
        $this->assertSame('Feinwerk Campaigns', $guide['title']);
        $this->assertSame('[FeinwerkCampaignScore_GuideTextGap:17]', $guide['text']);
        $this->assertCount(3, $guide['steps']);
        $this->assertSame('index.php?module=FeinwerkCampaignScore&action=downloadConvention', $guide['steps'][0]['url']);
        $this->assertTrue($guide['steps'][0]['download']);
        $this->assertSame(ConventionExport::IMPORT_URL, $guide['steps'][1]['url']);
        $this->assertFalse($guide['steps'][1]['download']);
        $this->assertSame($guide['url'], $guide['steps'][2]['url']);
    }

    public function test_atLeastOneMissingPointAndTheGeneralTextAtOneHundredOrWithoutData(): void
    {
        $translate = [self::class, 'translate'];
        $this->assertSame('[FeinwerkCampaignScore_GuideTextGap:1]', GuideBanner::variables(true, false, 99.8, null, 'en', $translate)['text']);
        $this->assertSame('[FeinwerkCampaignScore_GuideText]', GuideBanner::variables(true, false, 100.0, null, 'en', $translate)['text']);
        $this->assertSame('[FeinwerkCampaignScore_GuideText]', GuideBanner::variables(true, false, null, null, 'en', $translate)['text']);
    }

    /** Regression (UI review 2026-11-23): without a file to export, "Upload it" named a file nobody was offered. */
    public function test_withoutAConventionToExportTheGuideHasNoSteps(): void
    {
        $guide = GuideBanner::variables(true, false, null, null, 'en', [self::class, 'translate']);

        $this->assertSame([], $guide['steps']);
        $this->assertSame('[FeinwerkCampaignScore_GuideText]', $guide['text'], 'text and link stay');
        $this->assertSame([], GuideBanner::variables(true, false, 80.0, '', 'en', [self::class, 'translate'])['steps']);
    }

    public function test_theLinksCarryTheCampaignOfTheGuide(): void
    {
        $this->assertSame(
            'https://campaigns.feinwerk.io/de/matomo?mtm_campaign=plugin-promo&mtm_source=FeinwerkCampaignScore&mtm_content=report-guide',
            GuideBanner::url('de')
        );
        $this->assertStringStartsWith('https://campaigns.feinwerk.io/en/matomo?', GuideBanner::url('fr'));
    }

    /** The banners wait for promocard.js like every other notice of the kit; no showing rule without the ready mark. */
    public function test_theStylesheetHidesTheBannersUntilTheyAreChecked(): void
    {
        $css = (string) file_get_contents(__DIR__ . '/../../../stylesheets/guide.css');

        $this->assertSame(1, preg_match('/\.fwcs-banner:not\(\[data-fwpromo-ready\]\)\s*\{\s*display:\s*none/', $css));
        preg_match_all('/([^{}]+)\{([^}]*)\}/', (string) preg_replace('#/\*.*?\*/#s', '', $css), $rules, PREG_SET_ORDER);
        foreach ($rules as $rule) {
            if (!preg_match('/display:\s*(?!none)/', $rule[2])) {
                continue;
            }
            foreach (explode(',', $rule[1]) as $selector) {
                $selector = trim($selector);
                // only rules whose subject (the last compound) is a banner, with or without a modifier
                $parts = preg_split('/\s*[\s>+~]\s*/', $selector);
                $subject = (string) end($parts);
                if (preg_match('/\.fwcs-banner(--[\w-]+)?(?![\w-])/', $subject)) {
                    $this->assertStringContainsString('[data-fwpromo-ready]', $subject, $selector);
                }
            }
        }
    }

    /** Demo mode (test instances): the guide's × keeps it away for the browser session only. */
    public function test_inDemoTheGuideHidesForTheSessionOnly(): void
    {
        $translate = [self::class, 'translate'];
        $normal = GuideBanner::variables(true, false, 80.0, null, 'en', $translate);
        $demo = GuideBanner::variables(true, false, 80.0, null, 'en', $translate, true);

        $this->assertSame([90, 30, false], [$normal['days'], $normal['pauseDays'], $normal['demo']]);
        $this->assertSame([0, 0, true], [$demo['days'], $demo['pauseDays'], $demo['demo']], 'demo: no pause, every banner in turn');
    }

    /**
     * The place beside the score card is the kit's pausable place (promocard.js 5): any x empties it for 30 days, the
     * closed banner stays away longer; demo mode keeps the plain rotation group (Peter, 2026-11-24).
     */
    public function test_the_place_is_a_pausable_place_of_the_kit_except_in_demo(): void
    {
        $this->assertSame(30, \Piwik\Plugins\FeinwerkCampaignScore\Promo\PremiumPromo::PAUSE_DAYS);
        $this->assertGreaterThan(
            \Piwik\Plugins\FeinwerkCampaignScore\Promo\PremiumPromo::PAUSE_DAYS,
            \Piwik\Plugins\FeinwerkCampaignScore\Promo\PremiumPromo::ITEM_DAYS
        );
        $template = (string) file_get_contents(__DIR__ . '/../../../templates/_scoreSplit.twig');
        $this->assertStringContainsString(
            '{% if guide.pauseDays %} data-fwpromo-pausable="banner" data-fwpromo-pause="fwcs-guide" data-fwpromo-pause-days="{{ guide.pauseDays }}"{% else %} data-fwpromo-rotation="banner"{% endif %}',
            $template
        );
    }
}
