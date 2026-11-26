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
use Piwik\Plugins\FeinwerkCampaignScore\Check\Convention;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Finding;
use Piwik\Plugins\FeinwerkCampaignScore\Check\ValueRules;
use Piwik\Plugins\FeinwerkCampaignScore\Columns\FindingValue;
use Piwik\Plugins\FeinwerkCampaignScore\Data\ConventionFactory;
use Piwik\Plugins\FeinwerkCampaignScore\Data\PeriodGroups;
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
        $this->metrics = ['nb_visits', 'score_gain'];
        $this->processedMetrics = [];
        // what to fix first: the most score points first (UI review proposal a, Peter 2026-11-25 "alle")
        $this->defaultSortColumn = 'score_gain';
        $this->order = 3;
    }

    public function getMetrics()
    {
        return [
            'nb_visits' => Piwik::translate('General_ColumnNbVisits'),
            'score_gain' => Piwik::translate('FeinwerkCampaignScore_ColumnScoreGain'),
        ];
    }

    protected function getMetricsDocumentation()
    {
        return parent::getMetricsDocumentation() + [
            'score_gain' => Piwik::translate('FeinwerkCampaignScore_ScoreGainDocumentation'),
        ];
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
        // the fix right beside the value: Value | Suggestion | Visits; problem and field are a small line under the value
        // (UI review 2026-11-24, design D1); exports and the API keep every column
        // plus the score points of each fix, the table sorted by them (UI review proposal a)
        $view->config->columns_to_display = ['label', 'suggestion', 'score_gain', 'nb_visits'];
        $view->config->addTranslations([
            'label' => Piwik::translate('FeinwerkCampaignScore_ColumnValue'),
            'score_gain' => Piwik::translate('FeinwerkCampaignScore_ColumnScoreGain'),
            'type' => Piwik::translate('FeinwerkCampaignScore_ColumnProblem'),
            'field' => Piwik::translate('FeinwerkCampaignScore_ColumnField'),
            'suggestion' => Piwik::translate('FeinwerkCampaignScore_ColumnSuggestion'),
        ]);
        // an empty table means "clean" only when there were campaign visits to check (UI review 2026-11-23)
        $view->config->no_data_message = Piwik::translate(self::emptyMessageKey(self::checkBasis()));
        $view->config->datatable_css_class = 'fwcs-findings';
        // what to fix first: the most points first (Matomo's view falls back to visits otherwise); a click on a column
        // header still sorts by that column
        $view->requestConfig->filter_sort_column = 'score_gain';
        $view->requestConfig->filter_sort_order = 'desc';
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
        $canAccept = $idSite > 0 && Piwik::isUserHasAdminAccess($idSite);
        $view->config->custom_parameters['fwcs_can_accept'] = $canAccept ? 1 : 0;
        $acceptLabel = Piwik::translate('FeinwerkCampaignScore_AcceptRowAction');
        $acceptTitle = Piwik::translate('FeinwerkCampaignScore_AcceptRowActionTooltip');
        // an unusual medium goes into the list in the clean spelling the check compares (API addUsualMediums)
        $separator = $canAccept ? self::separator($idSite) : Convention::SEPARATOR_ANY;
        $mediumLabel = Piwik::translate('FeinwerkCampaignScore_AddMediumAction');
        $mediumTitle = Piwik::translate('FeinwerkCampaignScore_AddMediumActionTooltip');

        // the filter by type above the table (UI review proposal f); the table keeps it while paging and sorting
        $types = self::requestedTypes();
        if ($types !== []) {
            $view->config->custom_parameters['findingType'] = implode(',', $types);
        }
        $view->config->show_header_message = FindingTexts::filter(
            self::typeCounts(),
            $types,
            [Piwik::class, 'translate'],
            static function ($number): string {
                return (string) NumberFormatter::getInstance()->formatNumber($number);
            }
        );

        // readable texts only in the HTML table (as columns there); the API keeps the codes as metadata and the plain label
        $view->config->filters[] = static function (DataTable $table) use ($canAccept, $acceptLabel, $acceptTitle, $mediumLabel, $mediumTitle, $separator) {
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
                // Matomo formats the metric after these filters: the plus sign as the cell's prefix
                if ((float) $row->getColumn('score_gain') > 0) {
                    $row->setMetadata('html_column_score_gain_prefix', '+');
                }
                // no suggestion: for admins a button (Peter, 2026-11-24: "D5. ich folge deiner Empfehlung"; an unusual
                // medium goes into the usual mediums, proposal d), else what to do (D7). Matomo shows a cell's HTML
                // only next to a value
                $accept = $row->getMetadata('accept');
                // on phones the fix is the second line of the value (UI review 2026-11-26 D2; stylesheets hide the column)
                $inlineFix = '';
                if ((string) $row->getMetadata('suggestion') !== '') {
                    $inlineFix = '<span aria-hidden="true">&rarr;</span> ' . htmlspecialchars(
                        html_entity_decode((string) $row->getMetadata('suggestion'), ENT_QUOTES, 'UTF-8'),
                        ENT_QUOTES
                    );
                }
                if ((string) $row->getMetadata('suggestion') === '') {
                    $row->setColumn('suggestion', "\u{00A0}");
                    if ($canAccept && is_array($accept) && $accept !== []) {
                        // campaign values are stored HTML-encoded by Matomo: decoded here, escaped once by acceptButton
                        $shown = implode(', ', array_map(static function ($value): string {
                            return html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
                        }, $accept));
                        $medium = $type === Finding::UNUSUAL_MEDIUM;
                        if ($medium) {
                            $usual = array_values(array_unique(array_map(static function ($value) use ($separator): string {
                                $cleaned = ValueRules::cleaned((string) $value, $separator);
                                return $cleaned !== '' ? $cleaned : (string) $value;
                            }, $accept)));
                            // the dialog names and the request sends the spelling that goes into the list
                            $row->setMetadata('usual_medium', $usual);
                            $shown = implode(', ', $usual);
                        }
                        $inlineFix = self::acceptButton(
                            $medium ? $mediumLabel : $acceptLabel,
                            $medium ? $mediumTitle : $acceptTitle,
                            Piwik::translate($medium ? 'FeinwerkCampaignScore_AddMediumActionFor' : 'FeinwerkCampaignScore_AcceptRowActionFor', [$shown]),
                            $medium ? 'fwcs-medium-btn' : 'fwcs-accept-btn'
                        );
                    } else {
                        $inlineFix = '<span class="fwcs-hint">'
                            . htmlspecialchars(FindingTexts::hint($type, (string) $row->getMetadata('field'), $translate), ENT_QUOTES) . '</span>';
                    }
                    $row->setMetadata('html_column_suggestion_prefix', $inlineFix);
                }
                $prefix = '';
                if ($row->getMetadata('new')) {
                    $prefix .= '<span class="fwcs-new" title="'
                        . htmlspecialchars(Piwik::translate('FeinwerkCampaignScore_NewBadgeTitle'), ENT_QUOTES)
                        . '">' . htmlspecialchars(Piwik::translate('FeinwerkCampaignScore_NewBadge'), ENT_QUOTES) . '</span>';
                }
                // spelling variants as chips with their visits (proposal j); the plain label stays for screen readers
                if (in_array($type, [Finding::VARIANTS, Finding::SIMILAR], true) && is_array($values) && count($values) > 1) {
                    $prefix .= FindingTexts::chips($values, (string) $row->getMetadata('suggestion'), $formatNumber);
                }
                if ($prefix !== '') {
                    $row->setMetadata('html_label_prefix', $prefix);
                }
                $suffix = '<span class="fwcs-context fwcs-context--kind">'
                    . htmlspecialchars((string) $row->getColumn('type') . ' · ' . (string) $row->getColumn('field'), ENT_QUOTES)
                    . '</span>';
                $contexts = $row->getMetadata('contexts');
                if (is_array($contexts) && $contexts !== []) {
                    $suffix .= '<span class="fwcs-context">'
                        . htmlspecialchars(Piwik::translate('FeinwerkCampaignScore_ContextLabel', [FindingTexts::contexts($contexts, $translate)]), ENT_QUOTES)
                        . '</span>';
                }
                $row->setMetadata('html_label_suffix', '<span class="fwcs-fix-inline">' . $inlineFix . '</span>' . $suffix);
            }
        };

        if ($view instanceof HtmlTable) {
            $view->config->show_totals_row = false;
            // the link with the mark of Feinwerk Campaigns in place of the tip line (Peter, 2026-11-13)
            $view->config->show_footer_message = FeedbackBar::forReport(FeedbackBar::TOPIC_REPORT, $view, $idSite)
                . FooterLink::forReport($view);
        }
    }

    /**
     * The button that accepts the values of a finding without a suggestion (javascripts/accept.js handles it like the row
     * action, with Matomo's confirmation). Texts escaped. Pure.
     */
    public static function acceptButton(string $label, string $title, string $name = '', string $class = 'fwcs-accept-btn'): string
    {
        // $name: the button's name for screen readers with the row's value (UI review 2026-11-25 W2); $class:
        // fwcs-accept-btn (mark as correct) or fwcs-medium-btn (add to the usual mediums)
        return '<button type="button" class="' . htmlspecialchars($class, ENT_QUOTES) . ' fwcs-row-btn" title="' . htmlspecialchars($title, ENT_QUOTES) . '"'
            . ($name !== '' ? ' aria-label="' . htmlspecialchars($name, ENT_QUOTES) . '"' : '') . '>'
            . htmlspecialchars($label, ENT_QUOTES) . '</button>';
    }

    /** The separator of the website's convention ('any' on any error). */
    private static function separator(int $idSite): string
    {
        try {
            return ConventionFactory::forSite($idSite)->separator;
        } catch (\Throwable $e) {
            return Convention::SEPARATOR_ANY;
        }
    }

    /**
     * The finding types the request filters for (parameter findingType, comma separated); [] for all and for anything
     * that is no type (the API would refuse it).
     *
     * @return list<string>
     */
    private static function requestedTypes(): array
    {
        $types = array_values(array_unique(array_filter(array_map(
            'trim',
            explode(',', (string) Common::getRequestVar('findingType', '', 'string'))
        ), 'strlen')));
        foreach ($types as $type) {
            if (!in_array($type, Finding::TYPES, true)) {
                return [];
            }
        }
        return $types;
    }

    /**
     * How many findings of each type the period has (for the filter); [] for several periods and on any error. The
     * analysis is the one the table reads next (the API keeps it for the request).
     *
     * @return array<string, int>
     */
    private static function typeCounts(): array
    {
        try {
            $period = Common::getRequestVar('period', '', 'string');
            $date = Common::getRequestVar('date', '', 'string');
            if (PeriodGroups::isSeveral($period, $date)) {
                return [];
            }
            $table = \Piwik\API\Request::processRequest('FeinwerkCampaignScore.getFindings', [
                'idSite' => Common::getRequestVar('idSite', 0, 'int'),
                'period' => $period,
                'date' => $date,
                'segment' => (string) (\Piwik\API\Request::getRawSegmentFromRequest() ?: ''),
                'filter_limit' => -1,
                'format' => 'original',
            ], []);
            if (!$table instanceof DataTable) {
                return [];
            }
            $counts = [];
            foreach ($table->getRows() as $row) {
                $type = (string) $row->getMetadata('type');
                $counts[$type] = ($counts[$type] ?? 0) + 1;
            }
            return $counts;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * The empty table's message for the basis of the score (checkBasis()): "no campaign visits", "none could be checked"
     * or, when there were checked visits or the basis is unknown, "no problems found". Pure.
     *
     * @param array{campaign: int, checked: int}|null $basis
     */
    public static function emptyMessageKey(?array $basis): string
    {
        if ($basis === null || $basis['checked'] > 0) {
            return 'FeinwerkCampaignScore_NoProblems';
        }

        return $basis['campaign'] > 0 ? 'FeinwerkCampaignScore_ScoreNoneChecked' : 'FeinwerkCampaignScore_ScoreNoData';
    }

    /**
     * Campaign and checked visits of the requested website and period, null when unknown (several periods, an error).
     * Reads the score through the API with the same raw segment as the report's own request, so the analysis of this
     * request is shared (API keeps it per website, period, date and segment) instead of run twice.
     *
     * @return array{campaign: int, checked: int}|null
     */
    private static function checkBasis(): ?array
    {
        try {
            $period = Common::getRequestVar('period', '', 'string');
            $date = Common::getRequestVar('date', '', 'string');
            if (PeriodGroups::isSeveral($period, $date)) {
                return null;
            }
            $score = \Piwik\API\Request::processRequest('FeinwerkCampaignScore.getScore', [
                'idSite' => Common::getRequestVar('idSite', 0, 'int'),
                'period' => $period,
                'date' => $date,
                'segment' => (string) (\Piwik\API\Request::getRawSegmentFromRequest() ?: ''),
                'format' => 'original',
            ], []);
            if (!$score instanceof DataTable || $score->getRowsCount() !== 1) {
                return null;
            }
            $row = $score->getFirstRow();

            return ['campaign' => (int) $row->getColumn('campaign_visits'), 'checked' => (int) $row->getColumn('checked_visits')];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
