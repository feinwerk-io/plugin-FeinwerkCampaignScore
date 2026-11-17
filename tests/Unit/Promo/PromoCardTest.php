<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Promo;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Promo\PromoCard;

/**
 * The card in a dashboard widget (Peter, 2026-11-13: "Für das Dashboard Widget bitte die Werbung von ad 2: card nehmen",
 * "Als Blaupause für Dashboard widgets merken").
 *
 * @group FeinwerkCampaignScore
 */
class PromoCardTest extends TestCase
{
    public static function translate(string $key): string
    {
        return '[' . $key . ']';
    }

    public function test_noCardWhenPromotionsAreNotShownOrTheWidgetIsEmbedded(): void
    {
        $translate = [self::class, 'translate'];
        $this->assertNull(PromoCard::variables(false, false, 'de', $translate));
        $this->assertNull(PromoCard::variables(true, true, 'de', $translate));
    }

    public function test_theCardForFeinwerkCampaigns(): void
    {
        $card = PromoCard::variables(true, false, 'de', [self::class, 'translate']);

        $this->assertSame('campaigns-card', $card['id']);
        $this->assertSame(30, $card['days']);
        $this->assertSame('Feinwerk Campaigns', $card['name']);
        $this->assertSame('[FeinwerkCampaignScore_PromoCardText]', $card['text']);
        $this->assertSame('[FeinwerkCampaignScore_PromoCardLink]', $card['link']);
        $this->assertSame('[FeinwerkCampaignScore_PromoDismiss]', $card['dismiss']);
    }

    public function test_theLinkIsTheLandingPageWithTheCampaignOfTheCard(): void
    {
        $this->assertSame(
            'https://campaigns.feinwerk.io/de/matomo?mtm_campaign=plugin-promo&mtm_source=FeinwerkCampaignScore&mtm_content=dashboard-card',
            PromoCard::url('de')
        );
        $this->assertStringStartsWith('https://campaigns.feinwerk.io/en/matomo?', PromoCard::url('fr'));
    }

    /** The card stays hidden until its script has checked the 30 days, like every other notice of the kit. */
    public function test_theStylesheetHidesTheCardUntilItIsChecked(): void
    {
        $css = (string) file_get_contents(__DIR__ . '/../../../stylesheets/promocard.css');

        $this->assertSame(1, preg_match('/\.fwpromo-card:not\(\[data-fwpromo-ready\]\)\s*\{\s*display:\s*none/', $css));
    }

    /** Demo mode (test instances): the card's × keeps it away for the browser session only. */
    public function test_inDemoTheCardHidesForTheSessionOnly(): void
    {
        $card = PromoCard::variables(true, false, 'de', [self::class, 'translate'], true);

        $this->assertSame([0, true], [$card['days'], $card['demo']]);
    }
}
