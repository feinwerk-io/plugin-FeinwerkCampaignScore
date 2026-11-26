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
use Piwik\Plugins\FeinwerkCampaignScore\Ui\ScoreEvolution;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Evolution;

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
            // Campaign Score over time: a gap for periods without campaign visits instead of 0 % (Peter, 2026-11-14)
            'ViewDataTable.filterViewDataTable' => 'useScoreEvolution',
            // a line with the score and the findings under Matomo's campaign names report (Peter, 2026-11-24: "D4 ja")
            // (Matomo's own hook for the line under the campaign names report, core and Marketing Campaigns Reporting)
            'Template.afterCampaignsReport' => 'addCampaignsFooter',
            'ScheduledReports.processReports' => 'addScoreAlert',
        ];
    }

    /**
     * Under Matomo's campaign names report (Ui/CampaignsFooter): the score of the same website, period and segment with
     * its findings and a link to the Campaign Score report. Matomo calls this only for the report's own table (never for
     * its subtables); never breaks the report.
     *
     * @param string $out the footer HTML so far
     */
    public function addCampaignsFooter(&$out)
    {
        try {
            // embedded widgets (Widgetize, disableLink=1) link nowhere into Matomo: no line, and no analysis for it
            if (\Piwik\Plugins\FeinwerkCampaignScore\Feedback\FeedbackBar::isEmbedded(
                \Piwik\Piwik::getModule(),
                Common::getRequestVar('disableLink', 0, 'int')
            )) {
                return;
            }
            $period = Common::getRequestVar('period', '', 'string');
            $date = Common::getRequestVar('date', '', 'string');
            if (!\Piwik\Plugins\FeinwerkCampaignScore\Ui\CampaignsFooter::isFor(
                Common::getRequestVar('idSubtable', 0, 'int') > 0,
                \Piwik\Plugins\FeinwerkCampaignScore\Data\PeriodGroups::isSeveral($period, $date)
            )) {
                return;
            }
            $idSite = Common::getRequestVar('idSite', 0, 'int');
            $segment = (string) (\Piwik\API\Request::getRawSegmentFromRequest() ?: '');
            $table = \Piwik\API\Request::processRequest('FeinwerkCampaignScore.getScore', [
                'idSite' => $idSite,
                'period' => $period,
                'date' => $date,
                'segment' => $segment,
                'format' => 'original',
            ], []);
            $row = $table instanceof \Piwik\DataTable && $table->getRowsCount() === 1 ? $table->getFirstRow()->getColumns() : null;
            $formatter = \Piwik\NumberFormatter::getInstance();
            // first, right under the table: other plugins add to the same hook (Matomo's "Did you know?" box)
            $out = \Piwik\Plugins\FeinwerkCampaignScore\Ui\CampaignsFooter::html(
                $row,
                \Piwik\Plugins\FeinwerkCampaignScore\Ui\ScoreWidget::reportUrl($idSite, $period, $date, $segment),
                [\Piwik\Piwik::class, 'translate'],
                static function ($percent) use ($formatter): string {
                    return (string) $formatter->formatPercent($percent, 0);
                },
                static function ($number) use ($formatter): string {
                    return (string) $formatter->formatNumber($number);
                }
            ) . $out;
        } catch (\Throwable $e) {
            // the campaign report stays as Matomo shows it
        }
    }

    /**
     * Matomo's email reports: a score drop of 5 points or more leads the email, new findings are marked (Ui/ScoreAlert,
     * Peter 2026-11-25). Only the email (not SMS), only the plugin's own sections; never breaks the email.
     *
     * @param array<int, array<string, mixed>> $processedReports
     */
    public function addScoreAlert(&$processedReports, $reportType = null, $outputType = null, $report = null)
    {
        try {
            if ($reportType !== 'email' || !is_array($processedReports)) {
                return;
            }
            $formatter = \Piwik\NumberFormatter::getInstance();
            $processedReports = \Piwik\Plugins\FeinwerkCampaignScore\Ui\ScoreAlert::apply(
                $processedReports,
                [\Piwik\Piwik::class, 'translate'],
                static function ($percent) use ($formatter): string {
                    return (string) $formatter->formatPercent($percent, 0);
                },
                static function ($number) use ($formatter): string {
                    return (string) $formatter->formatNumber($number);
                }
            );
        } catch (\Throwable $e) {
            // the email goes out as Matomo builds it
        }
    }

    /** @param array<string, string> $visualizations */
    public function useScoreEvolution(&$visualizations)
    {
        try {
            $visualizations = ScoreUnit::visualizations($visualizations, self::scoreRequest(), Evolution::ID, ScoreEvolution::class);
        } catch (\Throwable $e) {
            // Matomo's own graph stays
        }
    }

    /** @return array<string, string> the request parameters that tell the plugin's own report */
    private static function scoreRequest(): array
    {
        $request = [];
        foreach (['module', 'action', 'apiModule', 'apiAction', 'moduleToWidgetize', 'actionToWidgetize'] as $name) {
            $request[$name] = Common::getRequestVar($name, '', 'string');
        }
        return $request;
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
        $unit = ScoreUnit::forColumn((string) $column, self::scoreRequest()) ?? $unit;
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = 'plugins/FeinwerkCampaignScore/javascripts/promo.js';
        $jsFiles[] = 'plugins/FeinwerkCampaignScore/javascripts/promocard.js';
        $jsFiles[] = 'plugins/FeinwerkCampaignScore/javascripts/connectorinstaller.js';
        $jsFiles[] = 'plugins/FeinwerkCampaignScore/javascripts/scoresplit.js';
        $jsFiles[] = 'plugins/FeinwerkCampaignScore/javascripts/feedback.js';
        $jsFiles[] = 'plugins/FeinwerkCampaignScore/javascripts/accept.js';
        $jsFiles[] = 'plugins/FeinwerkCampaignScore/javascripts/findings.js';
        $jsFiles[] = 'plugins/FeinwerkCampaignScore/javascripts/emailreport.js';
        $jsFiles[] = 'plugins/FeinwerkCampaignScore/javascripts/allsites.js';
    }

    /** Texts of the row action "accept value" (javascripts/accept.js). */
    public function getClientSideTranslationKeys(&$translationKeys)
    {
        foreach (['AcceptRowAction', 'AcceptRowActionTooltip', 'AcceptConfirm', 'AcceptConfirmText', 'AcceptFailed',
            'AddMediumConfirm', 'AddMediumConfirmAs', 'AddMediumConfirmText', 'AddMediumFailed', 'EmailReportManage', 'EmailReportDone',
            'EmailReportFailed'] as $key) {
            $translationKeys[] = 'FeinwerkCampaignScore_' . $key;
        }
        // Matomo's own button labels of the accept dialog
        foreach (['General_Yes', 'General_No', 'General_Ok'] as $key) {
            $translationKeys[] = $key;
        }
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = 'plugins/FeinwerkCampaignScore/stylesheets/campaignscore.css';
        $stylesheets[] = 'plugins/FeinwerkCampaignScore/stylesheets/promo.css';
        $stylesheets[] = 'plugins/FeinwerkCampaignScore/stylesheets/feinwerklogo.css';
        $stylesheets[] = 'plugins/FeinwerkCampaignScore/stylesheets/promocard.css';
        $stylesheets[] = 'plugins/FeinwerkCampaignScore/stylesheets/connectorinstaller.css';
        $stylesheets[] = 'plugins/FeinwerkCampaignScore/stylesheets/guide.css';
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
