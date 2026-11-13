<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Convention;
use Piwik\Plugins\FeinwerkCampaignScore\Check\MediumList;
use Piwik\Plugins\FeinwerkCampaignScore\Data\ConventionFactory;

/**
 * The convention of a website from the system settings and the website's own settings.
 *
 * @group FeinwerkCampaignScore
 */
class ConventionFactoryTest extends TestCase
{
    public function test_withoutSettings_theDefaults(): void
    {
        $convention = ConventionFactory::build([], []);

        $this->assertSame(MediumList::DEFAULTS, $convention->mediums->values());
        $this->assertSame([], $convention->accepted);
        $this->assertSame(Convention::SEPARATOR_ANY, $convention->separator);
        $this->assertNull($convention->namePattern);
        $this->assertTrue($convention->sourceAliases);
        $this->assertTrue($convention->typoHints);
    }

    public function test_systemSettings_setTheRulesForAllWebsites(): void
    {
        $convention = ConventionFactory::build(
            ['mediums' => "cpc\nemail", 'separator' => '_', 'namePattern' => '^[0-9]{4}_', 'sourceAliases' => false, 'typoHints' => '0'],
            []
        );

        $this->assertSame(['cpc', 'email'], $convention->mediums->values());
        $this->assertSame('_', $convention->separator);
        $this->assertSame('^[0-9]{4}_', $convention->namePattern);
        $this->assertFalse($convention->sourceAliases);
        $this->assertFalse($convention->typoHints);
    }

    public function test_websiteSettings_overrideTheSystemOnesWhereSet(): void
    {
        $convention = ConventionFactory::build(
            ['mediums' => "cpc\nemail", 'separator' => '_', 'namePattern' => '^[0-9]{4}_'],
            ['mediums' => "social\nnewsletter", 'accepted' => "e-mail\nPartner Mail", 'separator' => '-', 'namePattern' => '']
        );

        $this->assertSame(['social', 'newsletter'], $convention->mediums->values());
        $this->assertSame(['e-mail' => true, 'Partner Mail' => true], $convention->accepted);
        $this->assertSame('-', $convention->separator);
        $this->assertSame('^[0-9]{4}_', $convention->namePattern, 'an empty website pattern keeps the system pattern');
    }

    public function test_websiteSeparatorInherit_andUnknownValues_fallBack(): void
    {
        $this->assertSame('_', ConventionFactory::build(['separator' => '_'], ['separator' => 'inherit'])->separator);
        $this->assertSame(Convention::SEPARATOR_ANY, ConventionFactory::build(['separator' => 'x'], [])->separator);
        $this->assertNull(ConventionFactory::build(['namePattern' => '([a-z'], [])->namePattern, 'an invalid pattern is no pattern');
    }

    public function test_settingsRows_areReadLikeMatomoStoresThem(): void
    {
        $rows = [
            ['idsite' => '3', 'setting_name' => 'accepted', 'setting_value' => '"e-mail\nfb"', 'json_encoded' => '1'],
            ['idsite' => '3', 'setting_name' => 'separator', 'setting_value' => '-', 'json_encoded' => '0'],
            ['idsite' => '5', 'setting_name' => 'mediums', 'setting_value' => 'cpc', 'json_encoded' => '0'],
        ];

        $this->assertSame(
            [3 => ['accepted' => "e-mail\nfb", 'separator' => '-'], 5 => ['mediums' => 'cpc']],
            ConventionFactory::siteSettingsFromRows($rows)
        );
    }
}
