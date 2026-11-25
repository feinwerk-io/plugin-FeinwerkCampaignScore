<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Widgets;

use Piwik\Access;
use Piwik\API\Request;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\Http\BadRequestException;
use Piwik\Plugins\FeinwerkCampaignScore\Http\InvalidInputException;
use Piwik\NumberFormatter;
use Piwik\Piwik;
use Piwik\Plugins\FeinwerkCampaignScore\API;
use Piwik\Plugins\FeinwerkCampaignScore\Categories\CampaignScoreSubcategory;
use Piwik\Plugins\FeinwerkCampaignScore\Data\AllSites;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\ScoreCard;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\ScoreWidget;
use Piwik\Url;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;

/**
 * The score of every website the user can view, for the selected period (at the end of Acquisition > Campaign Score
 * and as a dashboard widget). Only shown to users with access to more than one website.
 */
class GetAllSites extends Widget
{
    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId('Referrers_Referrers');
        $config->setSubcategoryId(CampaignScoreSubcategory::ID);
        $config->setName('FeinwerkCampaignScore_AllSites');
        // report widgets get 100 + the report order: after the findings (103)
        $config->setOrder(200);
        $config->setIsWide();
        try {
            $config->setIsEnabled(count(Access::getInstance()->getSitesIdWithAtLeastViewAccess()) > 1);
        } catch (\Throwable $e) {
            $config->setIsEnabled(false);
        }
    }

    public function render()
    {
        $period = Common::getRequestVar('period', 'day', 'string');
        $date = Common::getRequestVar('date', 'yesterday', 'string');
        $segment = (string) Request::getRawSegmentFromRequest();
        $formatter = NumberFormatter::getInstance();

        $problem = AllSites::periodProblem($period, $date);
        if ($problem !== null) {
            // a date range would be computed for every website; several periods do not fit one table
            return $this->renderTemplate('_allSites', ['error' => Piwik::translate(
                $problem === AllSites::RANGE ? 'FeinwerkCampaignScore_ErrorRangeAllSites' : 'FeinwerkCampaignScore_ErrorSinglePeriod'
            )]);
        }

        $count = count(Access::getInstance()->getSitesIdWithAtLeastViewAccess());
        // many websites: only on a click (not with every page view); an embedded widget has no session for the click
        if ($count > AllSites::LOAD_WITH_PAGE_UP_TO && Common::getRequestVar('fwcs_load', 0, 'int') !== 1 && Piwik::getModule() !== 'Widgetize') {
            $query = [
                'module' => 'FeinwerkCampaignScore',
                'action' => 'getAllSites',
                'idSite' => Common::getRequestVar('idSite', 0, 'int'),
                'period' => $period,
                'date' => $date,
                'fwcs_load' => 1,
            ];
            return $this->renderTemplate('_allSites', [
                // the raw segment is already URL-encoded as Matomo expects it in a query string: appended as it is
                'loadUrl' => 'index.php?' . Url::getQueryStringFromParameters($query) . ($segment !== '' ? '&segment=' . $segment : ''),
                'loadLabel' => Piwik::translate('FeinwerkCampaignScore_AllSitesLoad', [$formatter->formatNumber(min($count, API::MAX_SITES))]),
            ]);
        }

        $sites = [];
        $error = null;
        $limited = null;
        try {
            $table = Request::processRequest('FeinwerkCampaignScore.getScoresForAllSites', [
                'period' => $period,
                'date' => $date,
                'segment' => $segment,
            ], []);
            if ($table instanceof DataTable && $table->getMetadata('fwcs_sites_total') > $table->getMetadata('fwcs_sites_shown')) {
                $limited = Piwik::translate(
                    $table->getMetadata('fwcs_sites_by_visits') ? 'FeinwerkCampaignScore_SitesLimited' : 'FeinwerkCampaignScore_SitesLimitedById',
                    [$formatter->formatNumber((int) $table->getMetadata('fwcs_sites_shown'))]
                );
            }
            foreach ($table instanceof DataTable ? $table->getRows() : [] as $row) {
                $score = $row->getColumn('score');
                $idSite = (int) $row->getMetadata('idsite');
                $sites[] = [
                    'name' => (string) $row->getColumn('label'),
                    'url' => ScoreWidget::reportUrl($idSite, $period, $date, $segment),
                    'score' => $score === false ? '' : $formatter->formatPercent((int) round((float) $score), 0),
                    'level' => $score === false ? '' : ((float) $score >= ScoreCard::GOOD_FROM ? 'good' : ((float) $score >= ScoreCard::OK_FROM ? 'ok' : 'poor')),
                    'visits' => $formatter->formatNumber((int) $row->getColumn('campaign_visits')),
                    'findings' => $formatter->formatNumber((int) $row->getColumn('findings')),
                ];
            }
        } catch (InvalidInputException | BadRequestException | \InvalidArgumentException $e) {
            $error = Piwik::translate('FeinwerkCampaignScore_ErrorSinglePeriod');
        } catch (\Exception $e) {
            $error = Piwik::translate('FeinwerkCampaignScore_ErrorLoading');
        }

        return $this->renderTemplate('_allSites', ['sites' => $sites, 'error' => $error, 'limited' => $limited]);
    }
}
