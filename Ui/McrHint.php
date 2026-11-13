<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Ui;

/**
 * The info box above the Campaign Score report while the free plugin Marketing Campaigns Reporting is not active:
 * install it, or activate it when it is already there. Nothing when it is active. Pure.
 */
final class McrHint
{
    public const INSTALL = 'install';
    public const ACTIVATE = 'activate';
    public const PLUGIN = 'MarketingCampaignsReporting';
    public const PLUGIN_PAGE = 'https://plugins.matomo.org/MarketingCampaignsReporting';

    /** null when the plugin is active: then there is nothing to say */
    public static function state(bool $inFilesystem, bool $activated): ?string
    {
        if ($activated) {
            return null;
        }
        return $inFilesystem ? self::ACTIVATE : self::INSTALL;
    }

    /**
     * Where the box links to: super users to Matomo's Marketplace (or its plugin list to activate), everyone else to
     * the public plugin page; null when only a super user can act and there is no page to show.
     *
     * @return array{url: string, label: string, external: bool}|null label is a translation key
     */
    public static function link(string $state, bool $isSuperUser, bool $marketplace): ?array
    {
        if ($state === self::ACTIVATE) {
            return $isSuperUser
                ? ['url' => 'index.php?module=CorePluginsAdmin&action=plugins', 'label' => 'FeinwerkCampaignScore_McrHintManage', 'external' => false]
                : null;
        }
        if ($isSuperUser && $marketplace) {
            return [
                'url' => 'index.php?module=Marketplace&action=overview#?popover=browsePluginDetail%243A' . self::PLUGIN,
                'label' => 'FeinwerkCampaignScore_McrHintMarketplace',
                'external' => false,
            ];
        }
        return ['url' => self::PLUGIN_PAGE, 'label' => 'FeinwerkCampaignScore_McrHintPluginPage', 'external' => true];
    }
}
