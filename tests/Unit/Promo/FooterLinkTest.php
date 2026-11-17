<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Promo;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Promo\FooterLink;

/**
 * The link with the mark of Feinwerk Campaigns under the findings (Peter, 2026-11-13).
 *
 * @group FeinwerkCampaignScore
 */
class FooterLinkTest extends TestCase
{
    public static function translate(string $key, array $args = []): string
    {
        return '[' . $key . ']';
    }

    public function test_noLinkWithoutPromotionsInEmbedsAndInSubtables(): void
    {
        $translate = [self::class, 'translate'];
        $this->assertNull(FooterLink::variables(false, false, false, 'en', $translate));
        $this->assertNull(FooterLink::variables(true, true, false, 'en', $translate));
        $this->assertNull(FooterLink::variables(true, false, true, 'en', $translate));
    }

    public function test_theLinkToFeinwerkCampaigns(): void
    {
        $link = FooterLink::variables(true, false, false, 'de', [self::class, 'translate']);

        $this->assertSame('[FeinwerkCampaignScore_FooterLinkText]', $link['text']);
        $this->assertSame(
            'https://campaigns.feinwerk.io/de/matomo?mtm_campaign=plugin-promo&mtm_source=FeinwerkCampaignScore&mtm_content=findings-footer',
            $link['url']
        );
    }
}
