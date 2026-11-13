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
}
