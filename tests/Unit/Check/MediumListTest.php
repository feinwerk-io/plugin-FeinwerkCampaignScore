<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Check;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Check\MediumList;
use Piwik\Plugins\FeinwerkCampaignScore\Check\ValueRules;

/**
 * @group FeinwerkCampaignScore
 */
class MediumListTest extends TestCase
{
    public function test_defaults_areTheCleanMediumsOfFeinwerkChannelGrouping(): void
    {
        $this->assertSame([
            'affiliate', 'app', 'audio', 'banner', 'cpa', 'cpc', 'cpm', 'cpv', 'display', 'e-mail', 'e_mail', 'email',
            'expandable', 'interstitial', 'link', 'mobile', 'newsletter', 'notification', 'organic', 'paid', 'ppc',
            'push', 'referral', 'retargeting', 'sm', 'sms', 'social', 'social-media', 'social-network', 'video',
        ], MediumList::DEFAULTS);
        foreach (MediumList::DEFAULTS as $medium) {
            $this->assertTrue(ValueRules::isClean($medium), $medium);
        }
    }

    public function test_fromText_readsOneMediumPerLineOrCommaSeparated(): void
    {
        $list = MediumList::fromText("cpc\n  Email \r\nsocial, video,,\n\ncpc");

        $this->assertSame(['cpc', 'email', 'social', 'video'], $list->values());
    }

    public function test_fromText_fallsBackToTheDefaultsWhenEmpty(): void
    {
        $this->assertSame(MediumList::DEFAULTS, MediumList::fromText(" \n , ")->values());
    }

    public function test_contains_matchesExactCleanValuesOnly(): void
    {
        $list = MediumList::fromText('email');

        $this->assertTrue($list->contains('email'));
        $this->assertFalse($list->contains('Email'));
        $this->assertFalse($list->contains('e-mail'));
    }
}
