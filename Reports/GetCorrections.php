<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugins\FeinwerkCampaignScore\Columns\FindingValue;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

/**
 * The correction list (FeinwerkCampaignScore.getCorrections) in Matomo's report metadata, so API clients and scheduled
 * email reports find it. No subcategory and no widget: on screen it is the CSV download under the findings table.
 *
 * The overview of all websites (getScoresForAllSites) is deliberately not a report: scheduled reports run with super
 * user rights, so an email report of a view user would list websites that user cannot see.
 */
class GetCorrections extends Report
{
    protected function init()
    {
        $this->categoryId = 'Referrers_Referrers';
        $this->name = Piwik::translate('FeinwerkCampaignScore_CorrectionsReport');
        $this->documentation = Piwik::translate('FeinwerkCampaignScore_CorrectionsDocumentation');
        $this->dimension = new FindingValue();
        $this->metrics = ['nb_visits'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = 'nb_visits';
        $this->order = 4;
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        // no widget: the list is the CSV download under the findings table
    }
}
