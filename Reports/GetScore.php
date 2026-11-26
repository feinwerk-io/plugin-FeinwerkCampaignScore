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
use Piwik\Plugins\FeinwerkCampaignScore\Data\CampaignReader;
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
        // with the clean share of each field, so API clients find them in the report metadata (proposal b); source and
        // medium only with Marketing Campaigns Reporting (Matomo's processed reports show a missing metric as 0).
        // previous_score and checked_visits feed the drop alert of the email report (Ui/ScoreAlert)
        $this->metrics = ['score', 'previous_score', 'checked_visits', 'score_name'];
        if (self::withSourceMedium()) {
            $this->metrics = array_merge($this->metrics, ['score_source', 'score_medium']);
        }
        $this->processedMetrics = [];
        $this->order = 2;
    }

    public function getMetrics()
    {
        return array_intersect_key([
            'score' => Piwik::translate('FeinwerkCampaignScore_ScoreMetric'),
            'previous_score' => Piwik::translate('FeinwerkCampaignScore_MetricPreviousScore'),
            'checked_visits' => Piwik::translate('FeinwerkCampaignScore_MetricCheckedVisits'),
            'score_name' => Piwik::translate('FeinwerkCampaignScore_MetricScoreName'),
            'score_source' => Piwik::translate('FeinwerkCampaignScore_MetricScoreSource'),
            'score_medium' => Piwik::translate('FeinwerkCampaignScore_MetricScoreMedium'),
        ], array_flip($this->metrics));
    }

    protected function getMetricsDocumentation()
    {
        return array_intersect_key([
            'score' => Piwik::translate('FeinwerkCampaignScore_ScoreMetricDocumentation'),
            'previous_score' => Piwik::translate('FeinwerkCampaignScore_MetricPreviousScoreDocumentation'),
            'checked_visits' => Piwik::translate('FeinwerkCampaignScore_MetricCheckedVisitsDocumentation'),
            'score_name' => Piwik::translate('FeinwerkCampaignScore_MetricScoreNameDocumentation'),
            'score_source' => Piwik::translate('FeinwerkCampaignScore_MetricScoreSourceDocumentation'),
            'score_medium' => Piwik::translate('FeinwerkCampaignScore_MetricScoreMediumDocumentation'),
        ], array_flip($this->metrics));
    }

    /** Whether source and medium are checked: Marketing Campaigns Reporting is activated (false on any error). */
    private static function withSourceMedium(): bool
    {
        try {
            return \Piwik\Plugin\Manager::getInstance()->isPluginActivated(CampaignReader::MCR);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Matomo's evolution graph; for this report it shows gaps for periods without campaign visits (Ui/ScoreUnit.php). */
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
