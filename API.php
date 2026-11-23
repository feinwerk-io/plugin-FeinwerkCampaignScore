<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore;

use Piwik\API\Request;
use Piwik\Cache;
use Piwik\Container\StaticContainer;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Plugins\FeinwerkCampaignScore\Http\InvalidInputException;
use Piwik\Piwik;
use Piwik\Plugin\Manager as PluginManager;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Analyzer;
use Piwik\Plugins\FeinwerkCampaignScore\Check\CampaignData;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Convention;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Result;
use Piwik\Plugins\FeinwerkCampaignScore\Data\AllSites;
use Piwik\Plugins\FeinwerkCampaignScore\Data\CampaignReader;
use Piwik\Plugins\FeinwerkCampaignScore\Data\ConventionExport;
use Piwik\Plugins\FeinwerkCampaignScore\Data\ConventionFactory;
use Piwik\Plugins\FeinwerkCampaignScore\Data\PeriodGroups;
use Piwik\Plugins\FeinwerkCampaignScore\Data\PreviousPeriod;
use Piwik\Plugins\FeinwerkCampaignScore\Data\ResultTables;
use Piwik\Plugins\FeinwerkCampaignScore\Feedback\FeedbackVotes;
use Piwik\Plugins\FeinwerkCampaignScore\Feedback\PluginFeedback;
use Piwik\Site;
use Piwik\Translation\Translator;
use Piwik\Version;

/**
 * How clean the campaign values of a website are: the score and the findings, computed from Matomo's archived
 * campaign reports for the requested period (nothing is stored but the accepted values of a website).
 *
 * @method static \Piwik\Plugins\FeinwerkCampaignScore\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    /** getScoresForAllSites reads at most this many websites (those with the most visits) */
    public const MAX_SITES = 100;

    /** @var array<string, array<string, array{metadata: array<string, mixed>, data: CampaignData, result: Result}>> */
    private $analyses = [];

    /**
     * The share of campaign visits with clean values and its basis: score (percent, missing without checkable
     * visits), previous_score (the period before, for a single period), clean_visits, checked_visits,
     * campaign_visits, unchecked_visits (rows grouped as "Others", masked values, visits Marketing Campaigns Reporting
     * has no values for), with_source_medium (1 when source and medium are known), findings.
     *
     * @param int $idSite
     * @param string $period
     * @param string $date
     * @param string|false $segment
     * @return DataTable|DataTable\Map
     */
    public function getScore($idSite, $period, $date, $segment = false)
    {
        // several periods (score over time, the small line): read a few at a time and only for the score
        $several = PeriodGroups::isSeveral((string) $period, (string) $date);
        return ResultTables::output(
            $this->analyze($idSite, $period, $date, $segment, $several),
            static function (Result $result) use ($several): DataTable {
                return ResultTables::score($result, !$several);
            }
        );
    }

    /**
     * The findings, most visits first: label (the value, or the spelling variants separated by ", ", empty for a
     * missing value) and nb_visits; as row metadata type (variants, alias, format, unusual_medium, separator,
     * pattern, incomplete, similar), field (name, keyword, source, medium, content, group, placement), suggestion,
     * values (value => visits), contexts (campaign name, source and medium where the values occur), new (1: not in
     * the previous period) and segment (the visits concerned).
     *
     * @param int $idSite
     * @param string $period
     * @param string $date
     * @param string|false $segment
     * @return DataTable|DataTable\Map
     */
    public function getFindings($idSite, $period, $date, $segment = false)
    {
        return ResultTables::output($this->analyze($idSite, $period, $date, $segment), [ResultTables::class, 'findings']);
    }

    /**
     * The correction list: one row per value to fix (label) with nb_visits and, as metadata, field, type and
     * suggestion.
     *
     * @param int $idSite
     * @param string $period
     * @param string $date
     * @param string|false $segment
     * @return DataTable|DataTable\Map
     */
    public function getCorrections($idSite, $period, $date, $segment = false)
    {
        return ResultTables::output($this->analyze($idSite, $period, $date, $segment), [ResultTables::class, 'corrections']);
    }

    /**
     * The clean campaign values of the website as a starting naming convention for Feinwerk Campaigns (format
     * feinwerk-campaign-convention, version 1). Counts only, nothing about visitors.
     *
     * @param int $idSite
     * @param string $period
     * @param string $date
     * @param string|false $segment
     * @return array<string, mixed>
     */
    public function getConvention($idSite, $period, $date, $segment = false)
    {
        $periods = $this->analyze($idSite, $period, $date, $segment);
        if (count($periods) !== 1) {
            throw new InvalidInputException(Piwik::translate('FeinwerkCampaignScore_ErrorSinglePeriod'));
        }
        $entry = reset($periods);
        $idSite = (int) $idSite;
        $periodObject = \Piwik\Period\Factory::build((string) $period, (string) $date, Site::getTimezoneFor($idSite));

        return ConventionExport::build(
            $entry['data'],
            $entry['result'],
            ConventionFactory::forSite($idSite),
            [
                'plugin_version' => (string) PluginManager::getInstance()->getLoadedPlugin(PluginFeedback::PLUGIN)->getVersion(),
                'matomo_version' => Version::VERSION,
                'site_id' => $idSite,
                'site_name' => (string) Site::getNameFor($idSite),
                'site_urls' => array_map('strval', (array) Request::processRequest('SitesManager.getSiteUrlsFromId', ['idSite' => $idSite], [])),
                'period' => [
                    'from' => $periodObject->getDateStart()->toString(),
                    'to' => $periodObject->getDateEnd()->toString(),
                ],
            ],
            gmdate('Y-m-d\TH:i:s\Z')
        );
    }

    /**
     * The score of every website the user can view, for one day, week, month or year: label (website name), score,
     * campaign_visits, checked_visits, findings; metadata idsite. At most MAX_SITES websites (with more, those with the
     * most visits), read in groups of AllSites::GROUP_SIZE; the result is kept for AllSites::CACHE_SECONDS.
     *
     * @param string $period
     * @param string $date
     * @param string|false $segment
     * @return DataTable
     */
    public function getScoresForAllSites($period, $date, $segment = false)
    {
        Piwik::checkUserHasSomeViewAccess();
        $period = (string) $period;
        $date = (string) $date;
        $problem = AllSites::periodProblem($period, $date);
        if ($problem !== null) {
            throw new InvalidInputException(Piwik::translate(
                $problem === AllSites::RANGE ? 'FeinwerkCampaignScore_ErrorRangeAllSites' : 'FeinwerkCampaignScore_ErrorSinglePeriod'
            ));
        }
        $segment = $segment === false || $segment === null ? '' : (string) $segment;

        $names = [];
        foreach ((array) Request::processRequest('SitesManager.getSitesWithAtLeastViewAccess', [], []) as $site) {
            $names[(int) $site['idsite']] = (string) $site['name'];
        }
        $idSites = array_keys($names);
        $byVisits = false;
        if (count($idSites) > self::MAX_SITES) {
            $visits = self::visitsBySite($period, $date);
            // without visits (the visits overview failed) the websites are chosen by ID
            $byVisits = $visits !== [];
            $idSites = AllSites::pick($idSites, $visits, self::MAX_SITES);
        }
        $conventions = ConventionFactory::forSites($idSites);

        $cache = Cache::getLazyCache();
        $key = AllSites::cacheKey(
            Piwik::getCurrentUserLogin(),
            $idSites,
            $period,
            $date,
            $segment,
            gmdate('Y-m-d'),
            md5(serialize($conventions))
        );
        $rows = $cache->fetch($key);
        if (!is_array($rows)) {
            $rows = $this->scoreRows($idSites, $conventions, $period, $date, $segment);
            $cache->save($key, $rows, AllSites::CACHE_SECONDS);
        }

        $table = new DataTable();
        foreach ($rows as $idSite => $columns) {
            $columns = ['label' => $names[$idSite] ?? (string) $idSite] + $columns;
            $table->addRow(new Row([Row::COLUMNS => $columns, Row::METADATA => ['idsite' => (int) $idSite]]));
        }
        $table->filter('Sort', ['campaign_visits', 'desc']);
        $table->setMetadata('fwcs_sites_total', count($names));
        $table->setMetadata('fwcs_sites_shown', count($idSites));
        $table->setMetadata('fwcs_sites_by_visits', $byVisits);

        return $table;
    }

    /**
     * The score columns by website, read in groups of at most AllSites::NAME_BUDGET campaign names and
     * AllSites::GROUP_SIZE websites: the report tables of a group are released before the next.
     *
     * @param list<int> $idSites
     * @param array<int, Convention> $conventions
     * @return array<int, array<string, int|float>>
     */
    private function scoreRows(array $idSites, array $conventions, string $period, string $date, string $segment): array
    {
        $reader = CampaignReader::create();
        $tables = DataTable\Manager::getInstance();
        // how large each website's reports are (campaign names), from the small report without keywords
        $names = [];
        foreach (AllSites::groups($idSites) as $group) {
            $before = $tables->getMostRecentTableId();
            $names += $reader->campaignNames($group, $period, $date, $segment);
            $tables->deleteAll($before);
        }
        $rows = [];
        foreach (AllSites::batches($idSites, $names) as $group) {
            $before = $tables->getMostRecentTableId();
            foreach ($reader->readSites($group, $period, $date, $segment) as $idSite => $data) {
                $result = Analyzer::analyze($data, $conventions[$idSite] ?? Convention::defaults());
                $columns = [];
                if ($result->score !== null) {
                    $columns['score'] = round($result->score * 100, 1);
                }
                $rows[$idSite] = $columns + [
                    'campaign_visits' => $result->campaignVisits,
                    'checked_visits' => $result->checkedVisits,
                    'findings' => count($result->findings),
                ];
            }
            $tables->deleteAll($before);
        }
        return $rows;
    }

    /**
     * Visits of every website the user can view, from Matomo's visits overview (small numeric archives, as in Matomo's
     * own overview of all websites); without segment, to choose the websites of the overview.
     *
     * @return array<int, int>
     */
    private static function visitsBySite(string $period, string $date): array
    {
        $visits = [];
        try {
            $result = Request::processRequest('VisitsSummary.get', [
                'idSite' => 'all',
                'period' => $period,
                'date' => $date,
                'columns' => 'nb_visits',
                'format_metrics' => 0,
            ], []);
            if ($result instanceof DataTable\Map) {
                foreach ($result->getDataTables() as $idSite => $child) {
                    $row = $child instanceof DataTable ? $child->getFirstRow() : false;
                    $visits[(int) $idSite] = $row ? (int) $row->getColumn('nb_visits') : 0;
                }
            }
        } catch (\Exception $e) {
            // without visits the websites are chosen by ID
        }
        return $visits;
    }

    /**
     * Marks values as accepted for the website: they count as clean from now on and no longer appear as findings
     * (setting "Accepted values" of the website). Admins of the website only.
     *
     * @param int $idSite
     * @param string|string[] $values one value, several separated by line breaks, or an array
     * @return array{accepted: int}
     */
    public function acceptValues($idSite, $values)
    {
        $idSite = (int) $idSite;
        Piwik::checkUserHasAdminAccess($idSite);
        if (!PluginFeedback::isAllowedMethod((string) ($_SERVER['REQUEST_METHOD'] ?? ''))) {
            throw new InvalidInputException('FeinwerkCampaignScore.acceptValues only accepts POST requests.');
        }
        $list = is_array($values) ? $values : preg_split('/\r\n|\r|\n/', (string) $values);
        $list = array_values(array_filter(array_map('trim', array_map('strval', $list ?: [])), 'strlen'));
        if ($list === []) {
            throw new InvalidInputException(Piwik::translate('FeinwerkCampaignScore_ErrorNoValue'));
        }

        $this->analyses = [];
        try {
            return ['accepted' => (new MeasurableSettings($idSite))->accept($list)];
        } catch (\LengthException $e) {
            // more accepted values than a website can keep: the translated limit
            throw new InvalidInputException($e->getMessage());
        }
    }

    /**
     * Thumbs up or down under the Campaign Score report or in its dashboard widget (Feinwerk feedback kit): the Matomo
     * server sends the vote to Feinwerk, never the browser. Only the plugin's own questions, one vote per user and
     * question every 10 seconds, nothing when feedback is switched off; store errors never reach the user.
     *
     * @param int $idSite
     * @param string $topic report or widget
     * @param string $vote up or down
     * @return array{sent: bool}
     */
    public function sendFeedback($idSite, $topic, $vote)
    {
        Piwik::checkUserIsNotAnonymous();
        Piwik::checkUserHasViewAccess((int) $idSite);
        if (!PluginFeedback::isAllowedMethod((string) ($_SERVER['REQUEST_METHOD'] ?? ''))) {
            throw new InvalidInputException('FeinwerkCampaignScore.sendFeedback only accepts POST requests.');
        }
        try {
            $payload = PluginFeedback::payload(
                (string) $topic,
                (string) $vote,
                (string) PluginManager::getInstance()->getLoadedPlugin(PluginFeedback::PLUGIN)->getVersion(),
                Version::VERSION,
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                (string) StaticContainer::get(Translator::class)->getCurrentLanguage()
            );
        } catch (\InvalidArgumentException $e) {
            throw new InvalidInputException(Piwik::translate('FeinwerkCampaignScore_ErrorFeedback'));
        }

        return ['sent' => FeedbackVotes::create()->cast(Piwik::getCurrentUserLogin(), $payload, PluginFeedback::enabled())];
    }

    /**
     * Score and findings per period, computed once per request for the same website, period, date and segment. A
     * single period is compared with the one before it (new findings, previous score).
     *
     * @param mixed $idSite
     * @param mixed $segment
     * @return array<string, array{metadata: array<string, mixed>, data: CampaignData, result: Result}>
     */
    /**
     * @param bool $scoreOnly for several periods: read only what the score needs (no findings of keyword, content,
     *        group and placement)
     */
    private function analyze($idSite, $period, $date, $segment, bool $scoreOnly = false): array
    {
        if (!is_numeric($idSite) || (int) $idSite <= 0) {
            throw new InvalidInputException('FeinwerkCampaignScore checks one website at a time: idSite must be a single website ID.');
        }
        $idSite = (int) $idSite;
        Piwik::checkUserHasViewAccess($idSite);

        $period = (string) $period;
        $date = (string) $date;
        $segment = $segment === false || $segment === null ? '' : (string) $segment;
        $key = implode("\n", [$idSite, $period, $date, $segment, (int) $scoreOnly]);
        if (isset($this->analyses[$key])) {
            return $this->analyses[$key];
        }

        $convention = ConventionFactory::forSite($idSite);
        $reader = CampaignReader::create();
        $entries = PeriodGroups::isSeveral($period, $date)
            ? self::readInGroups($reader, $idSite, $period, $date, $segment, $scoreOnly)
            : $reader->read($idSite, $period, $date, $segment);

        $previous = null;
        if (count($entries) === 1) {
            $previousPeriod = PreviousPeriod::of($period, $date, (string) Site::getTimezoneFor($idSite));
            if ($previousPeriod !== null) {
                $before = $reader->read($idSite, $previousPeriod['period'], $previousPeriod['date'], $segment);
                if (count($before) === 1) {
                    $previous = Analyzer::analyze(reset($before)['data'], $convention);
                }
            }
        }

        $periods = [];
        foreach ($entries as $label => $entry) {
            $periods[$label] = [
                'metadata' => $entry['metadata'],
                'data' => $entry['data'],
                'result' => Analyzer::analyze($entry['data'], $convention, $previous),
            ];
        }
        return $this->analyses[$key] = $periods;
    }

    /**
     * Several periods, PeriodGroups::SIZE at a time: the report tables of a group are released before the next one, so
     * the memory stays that of one group however many periods there are. The periods come from Matomo's small referrer
     * type report of the whole range (one table per period, with the period in its metadata).
     *
     * @return array<string, array{metadata: array<string, mixed>, data: CampaignData}>
     */
    private static function readInGroups(CampaignReader $reader, int $idSite, string $period, string $date, string $segment, bool $scoreOnly): array
    {
        $starts = [];
        $types = Request::processRequest('Referrers.getReferrerType', [
            'idSite' => $idSite,
            'period' => $period,
            'date' => $date,
            'segment' => $segment,
            'filter_limit' => -1,
            'disable_generic_filters' => 1,
            'format_metrics' => 0,
            'totals' => 0,
        ], []);
        if ($types instanceof DataTable\Map) {
            foreach ($types->getDataTables() as $child) {
                $periodOfTable = $child instanceof DataTable ? $child->getMetadata('period') : null;
                if ($periodOfTable instanceof \Piwik\Period) {
                    $starts[] = $periodOfTable->getDateStart()->toString();
                }
            }
        }
        if ($starts === []) {
            return $reader->read($idSite, $period, $date, $segment, $scoreOnly);
        }

        $tables = DataTable\Manager::getInstance();
        $entries = [];
        foreach (PeriodGroups::dates($starts) as $groupDate) {
            $before = $tables->getMostRecentTableId();
            $entries += $reader->read($idSite, $period, $groupDate, $segment, $scoreOnly);
            $tables->deleteAll($before);
        }
        return $entries;
    }
}
