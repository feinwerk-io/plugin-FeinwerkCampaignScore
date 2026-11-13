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
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Evolution;
use Piwik\Plugins\FeinwerkCampaignScore\Categories\CampaignScoreSubcategory;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

/**
 * The score over time (evolution graph on Acquisition > Campaign Score). As a report with the metric "score" it is also
 * available for Matomo's scheduled email reports and, where the plugin Custom Alerts is installed, for alerts when the
 * score falls below a threshold.
 */
class GetScore extends Report
{
    protected function init()
    {
        $this->categoryId = 'Referrers_Referrers';
        $this->subcategoryId = CampaignScoreSubcategory::ID;
        $this->name = Piwik::translate('FeinwerkCampaignScore_ScoreEvolution');
        $this->documentation = Piwik::translate('FeinwerkCampaignScore_ScoreDocumentation');
        $this->metrics = ['score'];
        $this->processedMetrics = [];
        $this->order = 2;
    }

    public function getMetrics()
    {
        return ['score' => Piwik::translate('FeinwerkCampaignScore_ScoreMetric')];
    }

    protected function getMetricsDocumentation()
    {
        return ['score' => Piwik::translate('FeinwerkCampaignScore_ScoreMetricDocumentation')];
    }

    public function getDefaultTypeViewDataTable()
    {
        return Evolution::ID;
    }

    public function alwaysUseDefaultViewDataTable()
    {
        return true;
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $widgetsList->addWidgetConfig(
            $factory->createWidget()->forceViewDataTable(Evolution::ID)->setIsWide()
        );
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->columns_to_display = ['score'];
        $view->config->addTranslation('score', Piwik::translate('FeinwerkCampaignScore_ScoreMetric'));
        if ($view instanceof Evolution) {
            $view->config->y_axis_unit = '%';
            $view->config->show_series_picker = false;
        }
    }
}
