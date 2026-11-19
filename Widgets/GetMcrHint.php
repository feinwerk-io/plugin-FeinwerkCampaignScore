<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Widgets;

use Piwik\Piwik;
use Piwik\Plugin\Manager as PluginManager;
use Piwik\Plugins\FeinwerkCampaignScore\Categories\CampaignScoreSubcategory;
use Piwik\Plugins\FeinwerkCampaignScore\ConnectorInstaller\ConnectorInstaller;
use Piwik\Plugins\FeinwerkCampaignScore\Promo\PromoLine;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\McrHint;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;

/**
 * An info box above the Campaign Score report while the free plugin Marketing Campaigns Reporting is not active
 * (Peter, 2026-11-13). Not on the dashboard.
 */
class GetMcrHint extends Widget
{
    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId('Referrers_Referrers');
        $config->setSubcategoryId(CampaignScoreSubcategory::ID);
        $config->setName('FeinwerkCampaignScore_McrHintName');
        // above every report of the page (report widgets get 100 + the report order, the score card 1)
        $config->setOrder(-10);
        $config->setIsWide();
        $config->setIsNotWidgetizable();
        try {
            $config->setIsEnabled(!PluginManager::getInstance()->isPluginActivated(McrHint::PLUGIN));
        } catch (\Throwable $e) {
            $config->setIsEnabled(false);
        }
    }

    public function render()
    {
        $manager = PluginManager::getInstance();
        $state = McrHint::state($manager->isPluginInFilesystem(McrHint::PLUGIN), $manager->isPluginActivated(McrHint::PLUGIN));
        if ($state === null) {
            return '';
        }
        $isSuperUser = Piwik::hasUserSuperUserAccess();
        $link = McrHint::link(
            $state,
            $isSuperUser,
            $manager->isPluginActivated('Marketplace'),
            PromoLine::isWordPress(),
            ConnectorInstaller::wordpressUrl()
        );
        return $this->renderTemplate('_mcrHint', [
            'text' => Piwik::translate($state === McrHint::ACTIVATE ? 'FeinwerkCampaignScore_McrHintActivate' : 'FeinwerkCampaignScore_McrHintInstall'),
            'askSuperUser' => !$isSuperUser ? Piwik::translate('FeinwerkCampaignScore_McrHintAskSuperUser') : null,
            'link' => $link === null ? null : ['url' => $link['url'], 'label' => Piwik::translate($link['label']), 'external' => $link['external']],
        ]);
    }
}
