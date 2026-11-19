<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Ui;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\McrHint;

/**
 * Peter, 2026-11-13: "Falls nicht installiert, per info Banner über den Reports angeben, dass das kostenlose Marketing
 * Campaigns Reporting Plugin installiert werden sollte" and "Wenn es installiert ist, dann keinen hinweis anzeigen".
 *
 * @group FeinwerkCampaignScore
 */
class McrHintTest extends TestCase
{
    public function test_noHintWhenThePluginIsActive(): void
    {
        $this->assertNull(McrHint::state(true, true));
    }

    public function test_installWhenItIsMissing(): void
    {
        $this->assertSame(McrHint::INSTALL, McrHint::state(false, false));
    }

    public function test_activateWhenItIsInstalledButNotActive(): void
    {
        $this->assertSame(McrHint::ACTIVATE, McrHint::state(true, false));
    }

    public function test_superUsersGoToTheMarketplaceOrThePluginList(): void
    {
        $install = McrHint::link(McrHint::INSTALL, true, true);
        $this->assertStringStartsWith('index.php?module=Marketplace&action=overview', $install['url']);
        $this->assertStringContainsString('browsePluginDetail', $install['url']);
        $this->assertFalse($install['external']);

        $this->assertSame('index.php?module=CorePluginsAdmin&action=plugins', McrHint::link(McrHint::ACTIVATE, true, true)['url']);
    }

    public function test_othersGetThePublicPluginPage(): void
    {
        $link = McrHint::link(McrHint::INSTALL, false, true);
        $this->assertSame(McrHint::PLUGIN_PAGE, $link['url']);
        $this->assertTrue($link['external']);
        $this->assertSame(McrHint::PLUGIN_PAGE, McrHint::link(McrHint::INSTALL, true, false)['url'], 'without the Marketplace plugin');
    }

    public function test_activatingNeedsASuperUser(): void
    {
        $this->assertNull(McrHint::link(McrHint::ACTIVATE, false, true));
    }

    /**
     * Regression (PluginLead's Matomo for WordPress test, 2026-11-17): Matomo's Marketplace answers 403 and the plugin
     * list 500 inside WordPress, so the hint links to the Marketplace in WordPress and drops the activate link.
     */
    public function test_insideWordPressSuperUsersGoToWordPressMarketplaceAndNotToMatomoPagesThatAreOff(): void
    {
        $wp = 'https://example.org/wp-admin/admin.php?page=matomo-marketplace&tab=install';
        $install = McrHint::link(McrHint::INSTALL, true, true, true, $wp);
        $this->assertSame($wp, $install['url']);
        $this->assertSame('FeinwerkCampaignScore_McrHintMarketplace', $install['label']);
        $this->assertFalse($install['external']);

        $this->assertNull(McrHint::link(McrHint::ACTIVATE, true, true, true, $wp), 'no link to the plugin list');
        $this->assertSame(McrHint::PLUGIN_PAGE, McrHint::link(McrHint::INSTALL, true, true, true, '')['url'], 'WordPress URL unknown');
        $this->assertSame(McrHint::PLUGIN_PAGE, McrHint::link(McrHint::INSTALL, false, true, true, $wp)['url'], 'admins');
        foreach ([McrHint::link(McrHint::INSTALL, true, true, true, $wp), McrHint::link(McrHint::INSTALL, true, true, true, '')] as $link) {
            $this->assertStringNotContainsString('module=Marketplace', $link['url']);
        }
    }
}
