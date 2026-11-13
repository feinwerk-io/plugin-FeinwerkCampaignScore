<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore;

use Piwik\Common;
use Piwik\Option;
use Piwik\Plugins\FeinwerkCampaignScore\Feedback\PluginFeedback;
use Piwik\Plugins\FeinwerkCampaignScore\Promo\PromoPlacements;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\ScoreUnit;

class FeinwerkCampaignScore extends \Piwik\Plugin
{
    public function registerEvents()
    {
        return [
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            // Feinwerk promotion kit (Promo/): banner on the admin home, context boxes on matching reports
            // the banner sits at the top of the Administration home (Peter, 2026-11-13: "Banner auf der Admin-Startseite nach oben")
            'Template.beforeContent' => 'addPromoBanner',
            'Widget.filterWidgets' => 'addPromoBoxes',
            // Feinwerk feedback kit: a deleted user's vote throttles go with the user
            'UsersManager.deleteUser' => 'onDeleteUser',
            // the score is a percentage in evolution graphs (Matomo 6 forecasts the open period by the unit)
            'Metrics.getEvolutionUnit' => 'getEvolutionUnit',
        ];
    }

    /**
     * @param string|null $unit
     * @param string $column
     * @param int|string $idSite
     */
    public function getEvolutionUnit(&$unit, $column, $idSite)
    {
        if (!empty($unit)) {
            return;
        }
        $request = [];
        foreach (['module', 'action', 'apiModule', 'apiAction', 'moduleToWidgetize', 'actionToWidgetize'] as $name) {
            $request[$name] = Common::getRequestVar($name, '', 'string');
        }
        $unit = ScoreUnit::forColumn((string) $column, $request) ?? $unit;
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = 'plugins/FeinwerkCampaignScore/javascripts/promo.js';
        $jsFiles[] = 'plugins/FeinwerkCampaignScore/javascripts/promocard.js';
        $jsFiles[] = 'plugins/FeinwerkCampaignScore/javascripts/feedback.js';
        $jsFiles[] = 'plugins/FeinwerkCampaignScore/javascripts/accept.js';
        $jsFiles[] = 'plugins/FeinwerkCampaignScore/javascripts/allsites.js';
    }

    /** Texts of the row action "accept value" (javascripts/accept.js). */
    public function getClientSideTranslationKeys(&$translationKeys)
    {
        foreach (['AcceptRowAction', 'AcceptRowActionTooltip', 'AcceptConfirm', 'AcceptDone'] as $key) {
            $translationKeys[] = 'FeinwerkCampaignScore_' . $key;
        }
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = 'plugins/FeinwerkCampaignScore/stylesheets/campaignscore.css';
        $stylesheets[] = 'plugins/FeinwerkCampaignScore/stylesheets/promo.css';
        $stylesheets[] = 'plugins/FeinwerkCampaignScore/stylesheets/promocard.css';
        $stylesheets[] = 'plugins/FeinwerkCampaignScore/stylesheets/feedback.css';
    }

    /**
     * Template.beforeContent on the Administration home page: the banner of the Feinwerk promotion kit at the top, once per
     * page across all Feinwerk plugins.
     *
     * @param string $out
     * @param string|null $type
     * @param string|null $module
     * @param string|null $action
     */
    public function addPromoBanner(&$out, $type = null, $module = null, $action = null)
    {
        if (PromoPlacements::isAdminHome($type, $module, $action)) {
            PromoPlacements::adminHome($out);
        }
    }

    /** Widget.filterWidgets: the context boxes of the Feinwerk promotion kit at the end of matching report pages. */
    public function addPromoBoxes($list)
    {
        PromoPlacements::addBoxes($list);
    }

    /** UsersManager.deleteUser: the user's feedback throttles (time of the last vote per question). */
    public function onDeleteUser($userLogin)
    {
        Option::deleteLike(PluginFeedback::userOptionPrefix((string) $userLogin) . '%');
    }

    public function uninstall()
    {
        // the feedback throttles of all users and the server's vote counter
        Option::deleteLike(PluginFeedback::THROTTLE_OPTION_PREFIX . '%');
    }
}
