<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Ui;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\Date;
use Piwik\NumberFormatter;
use Piwik\Piwik;
use Piwik\Plugin\Manager as PluginManager;
use Piwik\Plugins\FeinwerkCampaignScore\Categories\CampaignScoreSubcategory;
use Piwik\Plugins\FeinwerkCampaignScore\Data\CampaignReader;
use Piwik\Plugins\FeinwerkCampaignScore\Feedback\FeedbackBar;
use Piwik\Plugins\FeinwerkCampaignScore\Promo\GuideBanner;
use Piwik\Plugins\FeinwerkCampaignScore\Promo\PremiumPromo;
use Piwik\Plugins\FeinwerkCampaignScore\Promo\PromoCard;
use Piwik\Plugins\FeinwerkCampaignScore\Promo\PromoLine;
use Piwik\Site;
use Piwik\Url;
use Piwik\View;

/**
 * Renders templates/_scoreCard.twig for the current website, period and segment: on the report page the score with its
 * downloads (the findings table follows), and where promotions show the guide to Feinwerk Campaigns beside it
 * (templates/_scoreSplit.twig); in the dashboard widget the score, the three largest problems, a link to the report, the
 * card for Feinwerk Campaigns (Promo/PromoCard.php) and the feedback line (no tip line in the widget, Peter 2026-11-12).
 */
final class ScoreWidget
{
    public const TOP_PROBLEMS = 3;
    /** periods in the sparkline, the selected one included */
    public const SPARK_PERIODS = 8;

    public static function render(bool $asDashboardWidget): string
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');
        $period = Common::getRequestVar('period', 'day', 'string');
        $date = Common::getRequestVar('date', 'yesterday', 'string');
        $params = ['idSite' => $idSite, 'period' => $period, 'date' => $date, 'segment' => (string) Request::getRawSegmentFromRequest()];

        $score = self::lastTable(Request::processRequest('FeinwerkCampaignScore.getScore', $params, []));
        $scoreRow = $score ? $score->getFirstRow() : false;

        $findings = [];
        if ($asDashboardWidget) {
            $table = self::lastTable(Request::processRequest(
                'FeinwerkCampaignScore.getFindings',
                $params + ['filter_limit' => self::TOP_PROBLEMS],
                []
            ));
            foreach ($table ? $table->getRows() : [] as $row) {
                $findings[] = $row->getColumns() + (array) $row->getMetadata();
            }
        }

        $formatter = NumberFormatter::getInstance();
        $embedded = FeedbackBar::isEmbedded(Piwik::getModule(), Common::getRequestVar('disableLink', 0, 'int'));
        $card = ScoreCard::variables(
            $scoreRow ? $scoreRow->getColumns() : [],
            $findings,
            PluginManager::getInstance()->isPluginActivated(CampaignReader::MCR),
            static function (string $key, ...$args): string {
                return Piwik::translate($key, $args);
            },
            static function ($number) use ($formatter): string {
                return (string) $formatter->formatNumber($number);
            },
            static function ($percent) use ($formatter): string {
                return (string) $formatter->formatPercent($percent, 0);
            }
        );
        // nothing to download without campaign visits
        $downloads = $asDashboardWidget || empty($card['hasData'])
            ? []
            : self::downloads($idSite, $period, $date, $params['segment']);
        // the report page with promotions: the guide to Feinwerk Campaigns beside the score card, then the paid plugins
        $guide = null;
        if (!$asDashboardWidget) {
            $score = $scoreRow && !empty($card['hasData']) && is_numeric($scoreRow->getColumn('score'))
                ? (float) $scoreRow->getColumn('score')
                : null;
            $guide = GuideBanner::variables(PromoLine::shownToCurrentUser(), $embedded, $score,
                $downloads[1]['url'] ?? null, PromoLine::language(), [Piwik::class, 'translate'],
                PromoLine::shownToCurrentUser() && PromoLine::demo());
        }

        $view = new View($guide !== null ? '@FeinwerkCampaignScore/_scoreSplit' : '@FeinwerkCampaignScore/_scoreCard');
        $view->card = $card;
        $view->guide = $guide;
        $view->premium = $guide !== null ? PremiumPromo::plugins() : [];
        $view->askSuperUser = $view->premium !== [] ? PremiumPromo::askSuperUser() : '';
        $view->premiumDays = $guide !== null && $guide['demo'] ? 0 : GuideBanner::ITEM_DAYS;
        $view->premiumDemo = $guide !== null && $guide['demo'];
        $view->premiumLabel = Piwik::translate('FeinwerkCampaignScore_GuideLabel');
        $view->premiumCta = Piwik::translate('FeinwerkCampaignScore_GuideCta');
        $view->premiumDismiss = Piwik::translate('FeinwerkCampaignScore_GuideDismiss');
        $view->asDashboardWidget = $asDashboardWidget;
        $view->sparkline = self::sparkline($idSite, $period, $date, $params['segment']);
        $view->sparklineWidth = ScoreCard::SPARK_WIDTH;
        $view->sparklineHeight = ScoreCard::SPARK_HEIGHT;
        $view->reportUrl = self::reportUrl($idSite, $period, $date);
        $view->downloads = $downloads;
        $view->feedback = $asDashboardWidget && !$embedded ? FeedbackBar::forPage(FeedbackBar::TOPIC_WIDGET, $idSite) : '';
        // the card for Feinwerk Campaigns in the dashboard widget (house blueprint for dashboard widgets)
        $view->promoCard = $asDashboardWidget
            ? PromoCard::variables(PromoLine::shownToCurrentUser(), $embedded, PromoLine::language(), [Piwik::class, 'translate'],
                PromoLine::shownToCurrentUser() && PromoLine::demo())
            : null;

        return $view->render();
    }

    /**
     * The points of the sparkline: the score of the selected period and the SPARK_PERIODS - 1 before it, for a day,
     * week, month or year (one request per report for all of them). Null for a date range or on any error.
     */
    private static function sparkline(int $idSite, string $period, string $date, string $segment): ?string
    {
        try {
            if (!in_array($period, ['day', 'week', 'month', 'year'], true) || strpos($date, ',') !== false
                || preg_match('/^(last|previous)\d+$/', $date)
            ) {
                return null;
            }
            $end = Date::factory($date, (string) Site::getTimezoneFor($idSite));
            $start = $end->subPeriod(self::SPARK_PERIODS - 1, $period);
            $table = Request::processRequest('FeinwerkCampaignScore.getScore', [
                'idSite' => $idSite,
                'period' => $period,
                'date' => $start->toString() . ',' . $end->toString(),
                'segment' => $segment,
            ], []);
            if (!$table instanceof DataTable\Map) {
                return null;
            }
            $scores = [];
            foreach ($table->getDataTables() as $child) {
                $row = $child instanceof DataTable ? $child->getFirstRow() : false;
                $score = $row ? $row->getColumn('score') : false;
                $scores[] = $score === false ? null : (float) $score;
            }
            return ScoreCard::sparkline($scores);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * The download links of the report page: the correction list (CSV) and the starting convention for Feinwerk
     * Campaigns (JSON). Only for a single period.
     *
     * @return list<array{url: string, label: string}>
     */
    private static function downloads(int $idSite, string $period, string $date, string $segment): array
    {
        if ($period !== 'range' && (strpos($date, ',') !== false || preg_match('/^(last|previous)\d+$/', $date))) {
            return [];
        }
        $query = ['module' => 'FeinwerkCampaignScore', 'idSite' => $idSite, 'period' => $period, 'date' => $date];
        // the raw segment is already URL-encoded as Matomo expects it in a query string: appended as it is
        $segmentQuery = $segment !== '' ? '&segment=' . $segment : '';
        return [
            [
                'url' => 'index.php?' . Url::getQueryStringFromParameters(['action' => 'downloadCorrections'] + $query) . $segmentQuery,
                'label' => Piwik::translate('FeinwerkCampaignScore_DownloadCorrections'),
            ],
            [
                'url' => 'index.php?' . Url::getQueryStringFromParameters(['action' => 'downloadConvention'] + $query) . $segmentQuery,
                'label' => Piwik::translate('FeinwerkCampaignScore_DownloadConvention'),
            ],
        ];
    }

    /** Acquisition > Campaign Score for the same website, period and date. */
    public static function reportUrl(int $idSite, string $period, string $date): string
    {
        $query = ['idSite' => $idSite, 'period' => $period, 'date' => $date];

        return 'index.php?' . Url::getQueryStringFromParameters(['module' => 'CoreHome', 'action' => 'index'] + $query)
            . '#?' . Url::getQueryStringFromParameters($query + [
                'category' => 'Referrers_Referrers',
                'subcategory' => CampaignScoreSubcategory::ID,
            ]);
    }

    /** The table of the (last) period: the API returns a Map for several periods. */
    private static function lastTable($table): ?DataTable
    {
        if ($table instanceof DataTable\Map) {
            $tables = $table->getDataTables();
            $table = $tables === [] ? null : end($tables);
        }
        return $table instanceof DataTable ? $table : null;
    }
}
