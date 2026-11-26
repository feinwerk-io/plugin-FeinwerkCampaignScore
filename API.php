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
use Piwik\Plugins\FeinwerkCampaignScore\Http\ApiErrors;
use Piwik\Plugins\FeinwerkCampaignScore\Http\InvalidInputException;
use Piwik\Piwik;
use Piwik\Plugin\Manager as PluginManager;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Analyzer;
use Piwik\Plugins\FeinwerkCampaignScore\Check\CampaignData;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Convention;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Finding;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Result;
use Piwik\Plugins\FeinwerkCampaignScore\Check\ValueRules;
use Piwik\Plugins\FeinwerkCampaignScore\Data\AllSites;
use Piwik\Plugins\FeinwerkCampaignScore\Data\CampaignReader;
use Piwik\Plugins\FeinwerkCampaignScore\Data\ConventionExport;
use Piwik\Plugins\FeinwerkCampaignScore\Data\ConventionFactory;
use Piwik\Plugins\FeinwerkCampaignScore\Data\PeriodGroups;
use Piwik\Plugins\FeinwerkCampaignScore\Data\PreviousPeriod;
use Piwik\Plugins\FeinwerkCampaignScore\Data\ResultTables;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\EmailReport;
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
     * Returns the Campaign Score of one website and period: the share of campaign visits with clean campaign values. Use
     * it for "how clean is our campaign tracking", and with date=lastN for the trend.
     *
     * Columns: score (percent 0 to 100, one decimal; missing when the period has no checkable campaign visits),
     * previous_score (the period before, for a single period), clean_visits, checked_visits, campaign_visits,
     * unchecked_visits (visits in rows grouped as "Others", masked values, visits Marketing Campaigns Reporting has no
     * values for), with_source_medium (1 when source and medium are checked, that is with Marketing Campaigns Reporting),
     * score_name, score_source and score_medium (the share of each field's visits with a clean value, percent 0 to 100;
     * source and medium only with Marketing Campaigns Reporting; missing without visits), findings (the number of
     * findings; for several periods not computed) and fixed_findings (findings of the previous period that are fixed
     * now: the clean spelling is in use, or a missing value is filled; single period with a previous period only).
     *
     * @param int $idSite the website to check: one website ID (view access needed)
     * @param string $period day, week, month, year or range
     * @param string $date YYYY-MM-DD, today or yesterday; for period=range a range YYYY-MM-DD,YYYY-MM-DD or lastN/previousN;
     *                     for several periods of the same kind lastN or previousN (then one result per period)
     * @param string|false $segment a Matomo segment definition (URL-encoded) to check only those visits; default false: all visits
     * @return DataTable|DataTable\Map one row for a single period; a Map keyed by period for several periods
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
     * Returns what keeps campaign values from being clean, the most score points first (score_gain), each with a
     * suggestion. Use it to find out which campaign values to fix first and how.
     *
     * Columns: label (the value, or the spelling variants with their visits separated by ", "; empty for a missing
     * value), nb_visits (visits with the value) and score_gain (score points 0 to 100, one decimal, the finding
     * accounts for: its share of the visits that are not clean, a visit with two problems counting half for each, so
     * the score_gain of all findings adds up to 100 minus the score; 0 for typo hints and for keyword, content, group
     * and placement, which are not scored). Fix the findings with the highest score_gain first. Row metadata: type (variants, alias, format, unusual_medium,
     * separator, pattern, incomplete, similar), field (name, keyword, source, medium, content, group, placement),
     * suggestion (the clean value, empty where none can be given), values (value => visits), contexts (campaign name,
     * source and medium where the values occur), new (1: not in the previous period), accept (the values an admin can
     * accept with acceptValues) and segment (a segment for exactly these visits, for the Visits Log).
     *
     * @param int $idSite the website to check: one website ID (view access needed)
     * @param string $period day, week, month, year or range
     * @param string $date YYYY-MM-DD, today or yesterday; for period=range a range YYYY-MM-DD,YYYY-MM-DD or lastN/previousN;
     *                     for several periods of the same kind lastN or previousN (then one result per period)
     * @param string|false $segment a Matomo segment definition (URL-encoded) to check only those visits; default false: all visits
     * @param string $findingType only findings of these types, separated by commas: variants, alias, format,
     *                            unusual_medium, separator, pattern, incomplete, similar; default '': all findings.
     *                            Another value answers with HTTP 400
     * @return DataTable|DataTable\Map one table for a single period; a Map keyed by period for several periods
     */
    public function getFindings($idSite, $period, $date, $segment = false, $findingType = '')
    {
        // access first, then the parameters (house rule); analyze() checks the website again
        Piwik::checkUserHasViewAccess($this->oneSite($idSite));
        $types = self::findingTypes($findingType);
        $build = static function (Result $result) use ($types): DataTable {
            $table = ResultTables::findings($result);
            if ($types !== []) {
                $table->filter(static function (DataTable $table) use ($types): void {
                    foreach ($table->getRows() as $id => $row) {
                        if (!in_array((string) $row->getMetadata('type'), $types, true)) {
                            $table->deleteRow($id);
                        }
                    }
                });
            }
            return $table;
        };

        return ResultTables::output($this->analyze($idSite, $period, $date, $segment), $build);
    }

    /**
     * Returns the correction list: one row per campaign value to fix, with its suggestion. Use it to hand the fixes to
     * whoever builds the campaign links (format=csv gives the list as a CSV file).
     *
     * Columns: label (the value to fix) and nb_visits; row metadata field (name, keyword, source, medium, content, group,
     * placement), type (as in getFindings) and suggestion (the clean value, empty where none can be given).
     *
     * @param int $idSite the website to check: one website ID (view access needed)
     * @param string $period day, week, month, year or range
     * @param string $date YYYY-MM-DD, today or yesterday; for period=range a range YYYY-MM-DD,YYYY-MM-DD or lastN/previousN;
     *                     for several periods of the same kind lastN or previousN (then one result per period)
     * @param string|false $segment a Matomo segment definition (URL-encoded) to check only those visits; default false: all visits
     * @return DataTable|DataTable\Map one table for a single period; a Map keyed by period for several periods
     */
    public function getCorrections($idSite, $period, $date, $segment = false)
    {
        return ResultTables::output($this->analyze($idSite, $period, $date, $segment), [ResultTables::class, 'corrections']);
    }

    /**
     * Returns the website's clean campaign values of one period as a starting naming convention for Feinwerk Campaigns.
     * Use it to set up a naming convention from the values the team already uses. Counts only, nothing about visitors.
     *
     * Bad input (several periods) answers with HTTP 400.
     *
     * @param int $idSite the website to check: one website ID (view access needed)
     * @param string $period day, week, month, year or range
     * @param string $date YYYY-MM-DD, today or yesterday; for period=range a range YYYY-MM-DD,YYYY-MM-DD. One period only:
     *                     lastN or previousN for several periods is refused
     * @param string|false $segment a Matomo segment definition (URL-encoded) to check only those visits; default false: all visits
     * @return array<string, mixed> format feinwerk-campaign-convention, version 1: format, version, generated_at (ISO 8601
     *                              UTC), source (product, plugin and Matomo version, site_id, site_name, site_urls, period
     *                              from and to as YYYY-MM-DD), parameter_style (null), separator (- or _, null when mixed),
     *                              case (lower, or null when unknown), allowed_mediums, and fields with the clean values
     *                              per field and their visits
     */
    public function getConvention($idSite, $period, $date, $segment = false)
    {
        $periods = $this->analyze($idSite, $period, $date, $segment);
        if (count($periods) !== 1) {
            throw new InvalidInputException(ApiErrors::onePeriod($date, [Piwik::class, 'translate']));
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
     * Returns the Campaign Score of every website the user can view, for one day, week, month or year. Use it to compare
     * websites or to find the website whose campaign tracking needs work first.
     *
     * At most 100 websites (with more, the 100 with the most visits), read ten at a time; the result is kept for 15
     * minutes. A date range or several periods answer with HTTP 400.
     *
     * @param string $period day, week, month or year (no range)
     * @param string $date YYYY-MM-DD, today or yesterday: one period only
     * @param string|false $segment a Matomo segment definition (URL-encoded) to check only those visits; default false: all visits
     * @param int|bool|string $withPreviousPeriod 1 or true: also read the period before and add previous_score and
     *                                            score_change (this reads every website twice and takes about twice as
     *                                            long); 0 or false: not; default 0. Any other value answers with HTTP
     *                                            400. (Not "compare": Matomo uses that name for its own comparisons.)
     * @return DataTable one row per website: label (website name), score (percent, missing without checkable campaign
     *                   visits), campaign_visits, checked_visits, findings; with withPreviousPeriod also previous_score (percent)
     *                   and score_change (points, one decimal; both missing where a period has no score); row metadata
     *                   idsite
     */
    public function getScoresForAllSites($period, $date, $segment = false, $withPreviousPeriod = 0)
    {
        Piwik::checkUserHasSomeViewAccess();
        $period = (string) $period;
        $date = (string) $date;
        $problem = AllSites::periodProblem($period, $date);
        if ($problem !== null) {
            throw new InvalidInputException($problem === AllSites::RANGE
                ? ApiErrors::periodNotHere($period, ApiErrors::SINGLE_PERIODS, [Piwik::class, 'translate'])
                : ApiErrors::onePeriod($date, [Piwik::class, 'translate'], false));
        }
        $segment = $segment === false || $segment === null ? '' : (string) $segment;
        $compare = ApiErrors::flag($withPreviousPeriod);
        if ($compare === null) {
            throw new InvalidInputException(ApiErrors::badValue('withPreviousPeriod', $withPreviousPeriod, ApiErrors::FLAG_VALUES, [Piwik::class, 'translate']));
        }

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
            md5(serialize([$conventions, $compare]))
        );
        $rows = $cache->fetch($key);
        if (!is_array($rows)) {
            // the plain table (also the first part of the compared one, so a compare after the first load reads only
            // the period before)
            $plainKey = AllSites::cacheKey(
                Piwik::getCurrentUserLogin(),
                $idSites,
                $period,
                $date,
                $segment,
                gmdate('Y-m-d'),
                md5(serialize([$conventions, false]))
            );
            $rows = $compare ? $cache->fetch($plainKey) : false;
            if (!is_array($rows)) {
                $rows = $this->scoreRows($idSites, $conventions, $period, $date, $segment);
                $cache->save($plainKey, $rows, AllSites::CACHE_SECONDS);
            }
            // the trend per website (Peter, 2026-11-25 "alle", proposal 4): the period before, read the same way
            $previous = $compare ? PreviousPeriod::of($period, $date, 'UTC') : null;
            if ($previous !== null) {
                $rows = AllSites::withPrevious($rows, $this->scoreRows($idSites, $conventions, $previous['period'], $previous['date'], $segment));
                $cache->save($key, $rows, AllSites::CACHE_SECONDS);
            }
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
     * Marks campaign values as accepted for one website: they count as clean from now on and no longer appear as findings
     * (the website setting "Accepted values"). Use it when a value that looks wrong is right for this website; take one
     * back with removeAcceptedValues.
     *
     * Admins of the website only; POST only. At most 500 accepted values per website; bad input answers with HTTP 400.
     *
     * @param int $idSite the website: one website ID (admin access needed)
     * @param string|string[] $values the values exactly as they appear in the findings: one value, several separated by
     *                                line breaks, or an array
     * @param int|bool|string $dryRun 1 or true: only report what would change, save nothing; 0 or false: save; default 0.
     *                              Any other value answers with HTTP 400
     * @return array{accepted: int, added: list<string>, already: list<string>, dry_run: bool} accepted: the number of
     *         accepted values of the website afterwards; added: the values that are new; already: the values that were
     *         accepted before; dry_run: whether nothing was saved
     */
    public function acceptValues($idSite, $values, $dryRun = 0)
    {
        $idSite = $this->oneSite($idSite);
        Piwik::checkUserHasAdminAccess($idSite);
        if (!PluginFeedback::isAllowedMethod((string) ($_SERVER['REQUEST_METHOD'] ?? ''))) {
            throw new InvalidInputException('FeinwerkCampaignScore.acceptValues only accepts POST requests.');
        }
        $list = self::valueList($values);
        if ($list === []) {
            throw new InvalidInputException(Piwik::translate('FeinwerkCampaignScore_ErrorNoValue'));
        }
        $dryRun = self::dryRun($dryRun);

        $this->analyses = [];
        try {
            return (new MeasurableSettings($idSite))->accept($list, $dryRun) + ['dry_run' => $dryRun];
        } catch (\LengthException $e) {
            // more accepted values than a website can keep: the translated limit
            throw new InvalidInputException($e->getMessage());
        }
    }

    /**
     * Takes accepted campaign values back for one website: they are checked again and appear as findings where they do
     * not fit the rules. Use it to undo acceptValues.
     *
     * Admins of the website only; POST only. Values that are not accepted are reported, not refused; bad input (no
     * value) answers with HTTP 400.
     *
     * @param int $idSite the website: one website ID (admin access needed)
     * @param string|string[] $values the accepted values to take back: one value, several separated by line breaks, or an
     *                                array (as getRules lists them in accepted_values)
     * @param int|bool|string $dryRun 1 or true: only report what would change, save nothing; 0 or false: save; default 0.
     *                              Any other value answers with HTTP 400
     * @return array{accepted: int, removed: list<string>, not_found: list<string>, dry_run: bool} accepted: the number of
     *         accepted values afterwards; removed: the values taken back; not_found: values that were not accepted;
     *         dry_run: whether nothing was saved
     */
    public function removeAcceptedValues($idSite, $values, $dryRun = 0)
    {
        $idSite = $this->oneSite($idSite);
        Piwik::checkUserHasAdminAccess($idSite);
        if (!PluginFeedback::isAllowedMethod((string) ($_SERVER['REQUEST_METHOD'] ?? ''))) {
            throw new InvalidInputException('FeinwerkCampaignScore.removeAcceptedValues only accepts POST requests.');
        }
        $list = self::valueList($values);
        if ($list === []) {
            throw new InvalidInputException(Piwik::translate('FeinwerkCampaignScore_ErrorNoValue'));
        }
        $dryRun = self::dryRun($dryRun);

        $this->analyses = [];

        return (new MeasurableSettings($idSite))->removeAccepted($list, $dryRun) + ['dry_run' => $dryRun];
    }

    /**
     * Adds mediums to the usual mediums of one website, on top of the list in force (the website's own list or the list
     * for all websites): campaign visits with these mediums no longer count as "unusual medium". Use it when a medium
     * that Campaign Score reports as unusual is right for this website; take one back with removeUsualMediums.
     *
     * Admins of the website only; POST only. Stored in the website setting "Additional usual mediums" (at most 100);
     * bad input answers with HTTP 400.
     *
     * @param int $idSite the website: one website ID (admin access needed)
     * @param string|string[] $values the mediums: one, several separated by line breaks or commas, or an array; stored
     *                                in the clean spelling the check compares (Facebook Ads becomes facebook-ads, with
     *                                the website's separator)
     * @param int|bool|string $dryRun 1 or true: only report what would change, save nothing; 0 or false: save; default 0.
     *                              Any other value answers with HTTP 400
     * @return array{additional_mediums: int, added: list<string>, already: list<string>, dry_run: bool}
     *         additional_mediums: the number of the website's additional mediums afterwards; added: the mediums that are
     *         new; already: mediums that were usual before (in the list in force); dry_run: whether nothing was saved
     */
    public function addUsualMediums($idSite, $values, $dryRun = 0)
    {
        $idSite = $this->oneSite($idSite);
        Piwik::checkUserHasAdminAccess($idSite);
        if (!PluginFeedback::isAllowedMethod((string) ($_SERVER['REQUEST_METHOD'] ?? ''))) {
            throw new InvalidInputException('FeinwerkCampaignScore.addUsualMediums only accepts POST requests.');
        }
        $list = self::mediumList($values);
        if ($list === []) {
            throw new InvalidInputException(Piwik::translate('FeinwerkCampaignScore_ErrorNoValue'));
        }
        $dryRun = self::dryRun($dryRun);
        $convention = ConventionFactory::forSite($idSite);

        $this->analyses = [];
        try {
            return (new MeasurableSettings($idSite))->addMediums(
                self::cleanMediums($list, $convention->separator),
                $convention->mediums->values(),
                $dryRun
            ) + ['dry_run' => $dryRun];
        } catch (\LengthException $e) {
            throw new InvalidInputException($e->getMessage());
        }
    }

    /**
     * Takes mediums back out of the additional usual mediums of one website (those added with addUsualMediums or in the
     * website setting "Additional usual mediums"). The website's own list and the list for all websites stay as they are.
     *
     * Admins of the website only; POST only. Mediums that are not additional ones are reported, not refused; bad input
     * (no value) answers with HTTP 400.
     *
     * @param int $idSite the website: one website ID (admin access needed)
     * @param string|string[] $values the mediums to take back: one, several separated by line breaks or commas, or an
     *                                array (as getRules lists them in additional_mediums); compared in the clean
     *                                spelling, so "Facebook Ads" takes back facebook-ads
     * @param int|bool|string $dryRun 1 or true: only report what would change, save nothing; 0 or false: save; default 0.
     *                              Any other value answers with HTTP 400
     * @return array{additional_mediums: int, removed: list<string>, not_found: list<string>, dry_run: bool}
     *         additional_mediums: the number afterwards; removed: the mediums taken back; not_found: mediums that were
     *         no additional ones; dry_run: whether nothing was saved
     */
    public function removeUsualMediums($idSite, $values, $dryRun = 0)
    {
        $idSite = $this->oneSite($idSite);
        Piwik::checkUserHasAdminAccess($idSite);
        if (!PluginFeedback::isAllowedMethod((string) ($_SERVER['REQUEST_METHOD'] ?? ''))) {
            throw new InvalidInputException('FeinwerkCampaignScore.removeUsualMediums only accepts POST requests.');
        }
        $list = self::mediumList($values);
        if ($list === []) {
            throw new InvalidInputException(Piwik::translate('FeinwerkCampaignScore_ErrorNoValue'));
        }
        $dryRun = self::dryRun($dryRun);

        $this->analyses = [];

        // the same clean spelling as addUsualMediums stored: "Facebook Ads" finds facebook-ads
        return (new MeasurableSettings($idSite))->removeMediums(
            self::cleanMediums($list, ConventionFactory::forSite($idSite)->separator),
            $dryRun
        ) + ['dry_run' => $dryRun];
    }

    /**
     * Sends the Campaign Score of one website to the current user by email, every day, week or month: creates a Matomo
     * email report with the score (getScore) and the findings (getFindings). Use it when someone wants to follow the
     * score without opening Matomo. When the user already has such a report for the website, nothing new is created.
     *
     * Logged-in users with view access; POST only; needs Matomo's plugin "Email reports" (ScheduledReports). Change
     * or delete the report on Matomo's page of email reports (ScheduledReports.updateReport, deleteReport).
     *
     * @param int $idSite the website: one website ID (view access needed)
     * @param string $period how often: day, week or month; default week (sent every Monday for the week before)
     * @return array{idreport: int, created: bool, url: string} idreport: the email report's ID; created: false when the
     *         user had one already; url: Matomo's page of email reports, relative to Matomo's address
     */
    public function addEmailReport($idSite, $period = 'week')
    {
        $idSite = $this->oneSite($idSite);
        Piwik::checkUserIsNotAnonymous();
        Piwik::checkUserHasViewAccess($idSite);
        if (!PluginFeedback::isAllowedMethod((string) ($_SERVER['REQUEST_METHOD'] ?? ''))) {
            throw new InvalidInputException('FeinwerkCampaignScore.addEmailReport only accepts POST requests.');
        }
        $period = (string) $period;
        if (!in_array($period, EmailReport::PERIODS, true)) {
            throw new InvalidInputException(ApiErrors::badValue('period', $period, implode(', ', EmailReport::PERIODS), [Piwik::class, 'translate']));
        }
        if (!PluginManager::getInstance()->isPluginActivated('ScheduledReports')) {
            throw new InvalidInputException(Piwik::translate('FeinwerkCampaignScore_ErrorNoEmailReports'));
        }

        $existing = EmailReport::existing(
            (array) Request::processRequest('ScheduledReports.getReports', ['idSite' => $idSite], []),
            Piwik::getCurrentUserLogin()
        );
        if ($existing !== null) {
            return ['idreport' => (int) $existing['idreport'], 'created' => false, 'url' => EmailReport::manageUrl($idSite)];
        }
        $idReport = Request::processRequest('ScheduledReports.addReport', EmailReport::addReportParameters(
            $idSite,
            $period,
            Piwik::translate('FeinwerkCampaignScore_EmailReportDescription')
        ), []);

        return ['idreport' => (int) $idReport, 'created' => true, 'url' => EmailReport::manageUrl($idSite)];
    }

    /**
     * Returns the rules Campaign Score checks one website by, and where each rule comes from. Use it before changing a
     * rule, or to explain a finding (for example why a medium counts as unusual).
     *
     * Change the rules in the website settings or the settings for all websites (Matomo's SitesManager and
     * CorePluginsAdmin settings APIs, plugin FeinwerkCampaignScore), and accepted values with acceptValues and
     * removeAcceptedValues.
     *
     * @param int $idSite the website: one website ID (view access needed)
     * @return array<string, mixed> separator {value: any, hyphen or underscore; source}, name_pattern {value: a regular
     *         expression without delimiters, or null; source}, mediums {value: the usual mediums in force, lowercase, the
     *         additional ones included; source: of the base list}, additional_mediums (list, website only, see
     *         addUsualMediums), accepted_values (list, website only), source_aliases and typo_hints (true or false, all
     *         websites).
     *         source is website, all_websites or default
     */
    public function getRules($idSite)
    {
        $idSite = $this->oneSite($idSite);
        Piwik::checkUserHasViewAccess($idSite);

        return ConventionFactory::rulesForSite($idSite);
    }

    /**
     * The website of a request that works on one website: a positive website ID, else HTTP 400 naming idSite (before
     * the access check, so the message tells nothing about access).
     *
     * @param mixed $idSite
     */
    private function oneSite($idSite): int
    {
        if (!is_scalar($idSite) || !preg_match('/^\d+$/', trim((string) $idSite)) || (int) $idSite <= 0) {
            throw new InvalidInputException(ApiErrors::oneSite($idSite, [Piwik::class, 'translate']));
        }

        return (int) $idSite;
    }

    /**
     * The dryRun parameter: 1/true or 0/false (ApiErrors::flag), else HTTP 400 naming dryRun and the allowed values.
     *
     * @param mixed $dryRun
     */
    private static function dryRun($dryRun): bool
    {
        $flag = ApiErrors::flag($dryRun);
        if ($flag === null) {
            throw new InvalidInputException(ApiErrors::badValue('dryRun', $dryRun, ApiErrors::FLAG_VALUES, [Piwik::class, 'translate']));
        }

        return $flag;
    }

    /**
     * The mediums of a request: like valueList(), and each item also split at commas (the docblocks promise both).
     *
     * @param mixed $values
     * @return list<string>
     */
    private static function mediumList($values): array
    {
        $list = [];
        foreach (self::valueList($values) as $value) {
            foreach (explode(',', $value) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $list[] = $part;
                }
            }
        }
        return $list;
    }

    /**
     * Mediums in the clean spelling the check compares (FieldChecker: lowercase, words joined by the separator).
     *
     * @param list<string> $values
     * @return list<string>
     */
    private static function cleanMediums(array $values, string $separator): array
    {
        $clean = [];
        foreach ($values as $value) {
            $cleaned = ValueRules::cleaned((string) $value, $separator);
            $clean[] = $cleaned !== '' ? $cleaned : (string) $value;
        }
        return $clean;
    }

    /**
     * The findingType parameter: finding types separated by commas (Finding::TYPES), '' for all; else HTTP 400 naming
     * findingType and the allowed values.
     *
     * @param mixed $findingType
     * @param callable|null $translate like Piwik::translate (test seam; default Piwik::translate)
     * @return list<string>
     */
    private static function findingTypes($findingType, ?callable $translate = null): array
    {
        $translate = $translate ?? [Piwik::class, 'translate'];
        if (!is_scalar($findingType) && $findingType !== null) {
            throw new InvalidInputException(ApiErrors::badValue('findingType', '', implode(', ', Finding::TYPES), $translate));
        }
        $types = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $findingType)), 'strlen')));
        foreach ($types as $type) {
            if (!in_array($type, Finding::TYPES, true)) {
                throw new InvalidInputException(ApiErrors::badValue('findingType', $type, implode(', ', Finding::TYPES), $translate));
            }
        }
        return $types;
    }

    /**
     * The values of a request: one value, several separated by line breaks, or an array; trimmed, empty ones dropped.
     *
     * @param mixed $values
     * @return list<string>
     */
    private static function valueList($values): array
    {
        $list = is_array($values) ? $values : preg_split('/\r\n|\r|\n/', (string) $values);

        return array_values(array_filter(array_map('trim', array_map('strval', $list ?: [])), 'strlen'));
    }

    /**
     * Thumbs up or down under the Campaign Score report or in its dashboard widget (Feinwerk feedback kit): the Matomo
     * server sends the vote to Feinwerk, never the browser. Only the plugin's own questions, one vote per user and
     * question every 10 seconds, nothing when feedback is switched off; store errors never reach the user.
     *
     * Not needed for working with campaign data; POST only; unknown topics or votes answer with HTTP 400.
     *
     * @param int $idSite the website the report was shown for (view access needed)
     * @param string $topic report (under the Campaign Score report) or widget (in the dashboard widget)
     * @param string $vote up or down
     * @return array{sent: bool} sent: whether the vote went to Feinwerk (false when feedback is switched off or throttled)
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
        $idSite = $this->oneSite($idSite);
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
