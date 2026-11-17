<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Reports;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\NumberFormatter;
use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable;
use Piwik\Plugins\FeinwerkCampaignScore\Categories\CampaignScoreSubcategory;
use Piwik\Plugins\FeinwerkCampaignScore\Columns\FindingValue;
use Piwik\Plugins\FeinwerkCampaignScore\Feedback\FeedbackBar;
use Piwik\Plugins\FeinwerkCampaignScore\Promo\FooterLink;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\FindingTexts;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

/**
 * The findings table on Acquisition > Campaign Score, with the feedback question and the link to Feinwerk Campaigns below it.
 */
class GetFindings extends Report
{
    protected function init()
    {
        $this->categoryId = 'Referrers_Referrers';
        $this->subcategoryId = CampaignScoreSubcategory::ID;
        $this->name = Piwik::translate('FeinwerkCampaignScore_FindingsReport');
        $this->documentation = Piwik::translate('FeinwerkCampaignScore_FindingsDocumentation');
        $this->dimension = new FindingValue();
        $this->metrics = ['nb_visits'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = 'nb_visits';
        $this->order = 3;
    }

    /** Full width below the score card: the table has five columns. */
    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $widgetsList->addWidgetConfig($factory->createWidget()->setIsWide());
    }

    public function getDefaultTypeViewDataTable()
    {
        return HtmlTable::ID;
    }

    public function alwaysUseDefaultViewDataTable()
    {
        return true;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->columns_to_display = ['label', 'type', 'field', 'nb_visits', 'suggestion'];
        $view->config->addTranslations([
            'label' => Piwik::translate('FeinwerkCampaignScore_ColumnValue'),
            'type' => Piwik::translate('FeinwerkCampaignScore_ColumnProblem'),
            'field' => Piwik::translate('FeinwerkCampaignScore_ColumnField'),
            'suggestion' => Piwik::translate('FeinwerkCampaignScore_ColumnSuggestion'),
        ]);
        $view->config->no_data_message = Piwik::translate('FeinwerkCampaignScore_NoProblems');
        $view->config->datatable_css_class = 'fwcs-findings';
        $view->config->show_all_views_icons = false;
        $view->config->show_table_all_columns = false;
        $view->config->show_exclude_low_population = false;
        $view->config->show_flatten_table = false;
        $view->config->show_pivot_by_subtable = false;
        $view->config->show_insights = false;
        $view->config->show_goals = false;
        $view->config->show_related_reports = false;
        $view->config->show_export_as_rss_feed = false;
        // row actions: Matomo's segmented visitor log (rows carry a segment) and, for admins, "accept value"
        // (javascripts/accept.js); row evolution makes no sense for findings
        $view->config->disable_row_evolution = true;
        $view->config->disable_row_actions = false;
        $idSite = Common::getRequestVar('idSite', 0, 'int');
        $view->config->custom_parameters['fwcs_can_accept'] = $idSite > 0 && Piwik::isUserHasAdminAccess($idSite) ? 1 : 0;

        // readable texts only in the HTML table (as columns there); the API keeps the codes as metadata and the plain label
        $view->config->filters[] = static function (DataTable $table) {
            $formatter = NumberFormatter::getInstance();
            $formatNumber = static function ($number) use ($formatter): string {
                return (string) $formatter->formatNumber($number);
            };
            $translate = [Piwik::class, 'translate'];
            foreach ($table->getRows() as $row) {
                $type = (string) $row->getMetadata('type');
                $values = $row->getMetadata('values');
                $row->setColumn('label', FindingTexts::value($type, (string) $row->getColumn('label'), is_array($values) ? $values : [], $translate, $formatNumber));
                $row->setColumn('type', FindingTexts::type($type, $translate));
                $row->setColumn('field', FindingTexts::field((string) $row->getMetadata('field'), $translate));
                $row->setColumn('suggestion', (string) $row->getMetadata('suggestion'));
                if ($row->getMetadata('new')) {
                    $row->setMetadata('html_label_prefix', '<span class="fwcs-new" title="'
                        . htmlspecialchars(Piwik::translate('FeinwerkCampaignScore_NewBadgeTitle'), ENT_QUOTES)
                        . '">' . htmlspecialchars(Piwik::translate('FeinwerkCampaignScore_NewBadge'), ENT_QUOTES) . '</span>');
                }
                $contexts = $row->getMetadata('contexts');
                if (is_array($contexts) && $contexts !== []) {
                    $row->setMetadata('html_label_suffix', '<span class="fwcs-context">'
                        . htmlspecialchars(Piwik::translate('FeinwerkCampaignScore_ContextLabel') . ': ' . FindingTexts::contexts($contexts, $translate), ENT_QUOTES)
                        . '</span>');
                }
            }
        };

        if ($view instanceof HtmlTable) {
            $view->config->show_totals_row = false;
            // the link with the mark of Feinwerk Campaigns in place of the tip line (Peter, 2026-11-13)
            $view->config->show_footer_message = FeedbackBar::forReport(FeedbackBar::TOPIC_REPORT, $view, $idSite)
                . FooterLink::forReport($view);
        }
    }
}
