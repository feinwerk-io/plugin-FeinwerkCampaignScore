<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Data;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Piwik;
use Piwik\Plugin\Manager as PluginManager;
use Piwik\Plugins\FeinwerkCampaignScore\Check\CampaignData;

/**
 * Reads the campaign values of a website and period from Matomo's archived reports, through the reporting API only
 * (no log tables, no archives of its own): the campaign visits from Referrers.getReferrerType, then either Matomo's
 * campaign report (name and keyword) or, when Marketing Campaigns Reporting is activated, its name, keyword, content,
 * group, placement, source and medium reports and its source/medium report with the campaign names below each row. A fixed number of requests per
 * call, whatever the number of rows or periods.
 *
 * Rows Matomo grouped as "Others" (row limits of the referrer reports) and masked values (privacy setting "Campaign
 * parameter masking") cannot be checked: they count as present but unknown.
 */
final class CampaignReader
{
    public const MCR = 'MarketingCampaignsReporting';
    /** how Matomo stores a masked campaign value (PrivacyManager CampaignParameterValuesMasked, Matomo 5.5 and later) */
    public const MASKED_VALUE = '__discarded_by_policy__';
    /** campaign field => the report of Marketing Campaigns Reporting with its values */
    private const MCR_FIELDS = [
        'name' => 'getName',
        'keyword' => 'getKeyword',
        'source' => 'getSource',
        'medium' => 'getMedium',
        'content' => 'getContent',
        'group' => 'getGroup',
        'placement' => 'getPlacement',
    ];
    private const MCR_REPORTS = ['getName', 'getKeyword', 'getContent', 'getGroup', 'getPlacement', 'getSource', 'getMedium'];
    /** the reports the score needs (campaign name, source and medium; keyword, content, group and placement are not scored) */
    private const MCR_SCORE_REPORTS = ['getName', 'getSource', 'getMedium'];
    /** separator of Marketing Campaigns Reporting between source and medium in its source/medium report */
    private const SOURCE_MEDIUM_SEPARATOR = ' - ';

    /** @var callable(string, array<string, mixed>): DataTable\DataTableInterface */
    private $request;

    /** @var bool */
    private $withMcr;

    /** @var array<string, true> */
    private $masked;

    /**
     * @param callable(string, array<string, mixed>): DataTable\DataTableInterface $request
     * @param list<string> $maskedValues labels of masked values (stored and translated form)
     */
    public function __construct(callable $request, bool $withMcr, array $maskedValues = [self::MASKED_VALUE])
    {
        $this->request = $request;
        $this->withMcr = $withMcr;
        $this->masked = array_fill_keys($maskedValues, true);
    }

    /** With Matomo's API as the current user. */
    public static function create(): self
    {
        $request = static function (string $method, array $params) {
            // an empty default request: nothing of the current URL (filters, comparisons) leaks into these requests
            return Request::processRequest($method, $params, []);
        };

        return new self(
            $request,
            PluginManager::getInstance()->isPluginActivated(self::MCR),
            // Marketing Campaigns Reporting shows a masked value translated (PrivacyManager_CampaignParameterDiscarded)
            [self::MASKED_VALUE, Piwik::translate('PrivacyManager_CampaignParameterDiscarded')]
        );
    }

    /**
     * @param string $segment '' for none
     * @param bool $scoreOnly only the reports the score needs (the score over time): the result has no keywords,
     *        contents, groups and placements, so its findings cover campaign name, source and medium only
     * @return array<string, array{metadata: array<string, mixed>, data: CampaignData}> one entry per period, keyed by
     *         the period label of Matomo's DataTable\Map ('' for one period)
     */
    public function read(int $idSite, string $period, string $date, string $segment, bool $scoreOnly = false): array
    {
        $params = [
            'idSite' => $idSite,
            'period' => $period,
            'date' => $date,
            'segment' => $segment,
            'filter_limit' => -1,
            'disable_generic_filters' => 1,
            'format_metrics' => 0,
            'totals' => 0,
        ];
        $types = self::periods(($this->request)('Referrers.getReferrerType', $params));

        $mcr = [];
        if ($this->withMcr) {
            foreach ($scoreOnly ? self::MCR_SCORE_REPORTS : self::MCR_REPORTS as $method) {
                $mcr[$method] = self::periods(($this->request)('MarketingCampaignsReporting.' . $method, $params));
            }
            $mcr['getSourceMedium'] = self::periods(
                ($this->request)('MarketingCampaignsReporting.getSourceMedium', ['expanded' => 1] + $params)
            );
        }

        $campaigns = null;
        $result = [];
        foreach ($types as $label => $typesTable) {
            $campaignVisits = self::campaignVisits($typesTable);
            if ($this->withMcr && !self::mcrIsEmpty($mcr, $label, $campaignVisits)) {
                $data = $this->fromMcr($campaignVisits, $mcr, $label);
            } else {
                // without Marketing Campaigns Reporting, or a period from before it collected data
                $campaigns = $campaigns ?? self::periods(
                    ($this->request)('Referrers.getCampaigns', ($scoreOnly ? [] : ['expanded' => 1]) + $params)
                );
                $data = $this->fromReferrers($campaignVisits, $campaigns[$label] ?? new DataTable());
            }
            $result[$label] = ['metadata' => $typesTable->getAllTableMetadata(), 'data' => $data];
        }

        return $result;
    }

    /**
     * Several websites for one period at once, one request per report (the overview of all websites). Matomo's
     * Referrers.getReferrerType takes a single website, so the campaign visits are the sum of Matomo's campaign report
     * (its Others row included), which counts the same visits.
     *
     * @param list<int> $idSites
     * @param string $segment '' for none
     * @return array<int, CampaignData> by website
     */
    public function readSites(array $idSites, string $period, string $date, string $segment): array
    {
        $idSites = array_values(array_map('intval', $idSites));
        if ($idSites === []) {
            return [];
        }
        $params = [
            'idSite' => implode(',', $idSites),
            'period' => $period,
            'date' => $date,
            'segment' => $segment,
            'filter_limit' => -1,
            'disable_generic_filters' => 1,
            'format_metrics' => 0,
            'totals' => 0,
        ];
        // the visits per website from Matomo's campaign report: with Marketing Campaigns Reporting without its keywords
        // (they are read below, only for the websites that need them), else with them in the same request
        $campaigns = self::bySite(
            ($this->request)('Referrers.getCampaigns', ($this->withMcr ? [] : ['expanded' => 1]) + $params),
            $idSites
        );

        $mcr = [];
        if ($this->withMcr) {
            foreach (self::MCR_REPORTS as $method) {
                $mcr[$method] = self::bySite(($this->request)('MarketingCampaignsReporting.' . $method, $params), $idSites);
            }
            $mcr['getSourceMedium'] = self::bySite(
                ($this->request)('MarketingCampaignsReporting.getSourceMedium', ['expanded' => 1] + $params),
                $idSites
            );
        }

        $visits = [];
        $withoutMcr = [];
        foreach ($idSites as $idSite) {
            $label = (string) $idSite;
            $visits[$idSite] = self::totalVisits($campaigns[$label] ?? new DataTable());
            if (!$this->withMcr || self::mcrIsEmpty($mcr, $label, $visits[$idSite])) {
                $withoutMcr[] = $idSite;
            }
        }
        // names and keywords from Matomo's campaign report only for the websites without values of Marketing Campaigns
        // Reporting (all of them when it is not activated)
        $expanded = !$this->withMcr ? $campaigns : ($withoutMcr === [] ? [] : self::bySite(
            ($this->request)('Referrers.getCampaigns', ['expanded' => 1, 'idSite' => implode(',', $withoutMcr)] + $params),
            $withoutMcr
        ));

        $result = [];
        foreach ($idSites as $idSite) {
            $label = (string) $idSite;
            $result[$idSite] = in_array($idSite, $withoutMcr, true)
                ? $this->fromReferrers($visits[$idSite], $expanded[$label] ?? new DataTable())
                : $this->fromMcr($visits[$idSite], $mcr, $label);
        }
        return $result;
    }

    /**
     * The number of campaign names per website (rows of Matomo's campaign report, without its keywords): a cheap
     * measure of how large the reports of a website are, to read the overview in bounded groups.
     *
     * @param list<int> $idSites
     * @return array<int, int>
     */
    public function campaignNames(array $idSites, string $period, string $date, string $segment): array
    {
        $idSites = array_values(array_map('intval', $idSites));
        if ($idSites === []) {
            return [];
        }
        $tables = self::bySite(($this->request)('Referrers.getCampaigns', [
            'idSite' => implode(',', $idSites),
            'period' => $period,
            'date' => $date,
            'segment' => $segment,
            'filter_limit' => -1,
            'disable_generic_filters' => 1,
            'format_metrics' => 0,
            'totals' => 0,
        ]), $idSites);
        $names = [];
        foreach ($idSites as $idSite) {
            $table = $tables[(string) $idSite] ?? null;
            $names[$idSite] = $table instanceof DataTable ? count($table->getRowsWithoutSummaryRow()) : 0;
        }
        return $names;
    }

    /**
     * @param list<int> $idSites
     * @return array<string, DataTable> keyed by website ID
     */
    private static function bySite(DataTable\DataTableInterface $table, array $idSites): array
    {
        if ($table instanceof DataTable\Map) {
            return self::periods($table);
        }
        return $table instanceof DataTable && count($idSites) === 1 ? [(string) $idSites[0] => $table] : [];
    }

    /**
     * @return array<string, DataTable>
     */
    private static function periods(DataTable\DataTableInterface $table): array
    {
        if ($table instanceof DataTable\Map) {
            $tables = [];
            foreach ($table->getDataTables() as $label => $child) {
                if ($child instanceof DataTable) {
                    $tables[(string) $label] = $child;
                }
            }
            return $tables;
        }

        return $table instanceof DataTable ? ['' => $table] : [];
    }

    private static function campaignVisits(DataTable $types): int
    {
        foreach ($types->getRowsWithoutSummaryRow() as $row) {
            $type = $row->getMetadata('referrer_type');
            if ($type === false) {
                $type = $row->getColumn('label');
            }
            if ((int) $type === Common::REFERRER_TYPE_CAMPAIGN) {
                return self::visits($row);
            }
        }
        return 0;
    }

    /**
     * @param array<string, array<string, DataTable>> $mcr
     */
    private static function mcrIsEmpty(array $mcr, string $label, int $campaignVisits): bool
    {
        if ($campaignVisits <= 0) {
            return false;
        }
        foreach (['getSource', 'getMedium', 'getSourceMedium'] as $method) {
            if (isset($mcr[$method][$label]) && $mcr[$method][$label]->getRowsCount() > 0) {
                return false;
            }
        }
        return true;
    }

    private function fromReferrers(int $campaignVisits, DataTable $campaigns): CampaignData
    {
        $names = [];
        $keywords = [];
        $units = [];
        $others = 0;
        foreach (self::rows($campaigns) as $row) {
            $name = self::label($row);
            $visits = self::visits($row);
            if ($name === '' || $this->isMasked($name)) {
                $others += $visits;
                continue;
            }
            $names[$name] = ($names[$name] ?? 0) + $visits;
            $units[] = ['name' => $name, 'source' => null, 'medium' => null, 'visits' => $visits];

            $subtable = $row->getSubtable();
            if ($subtable instanceof DataTable) {
                foreach (self::rows($subtable) as $keywordRow) {
                    $keyword = self::label($keywordRow);
                    if ($keyword !== '' && !$this->isMasked($keyword)) {
                        $keywords[$keyword] = ($keywords[$keyword] ?? 0) + self::visits($keywordRow);
                    }
                }
            }
        }
        $others += self::othersVisits($campaigns);
        if ($campaignVisits <= 0) {
            $campaignVisits = (int) array_sum($names) + $others;
        }

        return new CampaignData($campaignVisits, false, ['name' => $names, 'keyword' => $keywords], ['name' => $others], $units, $others);
    }

    /**
     * @param array<string, array<string, DataTable>> $mcr
     */
    private function fromMcr(int $campaignVisits, array $mcr, string $label): CampaignData
    {
        $fields = [];
        $fieldOthers = [];
        foreach (self::MCR_FIELDS as $field => $method) {
            [$fields[$field], $fieldOthers[$field]] = $this->values($mcr[$method][$label] ?? new DataTable());
        }

        $units = [];
        $unchecked = 0;
        $sourceMedium = $mcr['getSourceMedium'][$label] ?? new DataTable();
        // visits with a source or a medium, and those of them with a campaign name
        $withSourceOrMedium = self::othersVisits($sourceMedium);
        $namedWithSourceOrMedium = $withSourceOrMedium;
        foreach (self::rows($sourceMedium) as $row) {
            $visits = self::visits($row);
            $withSourceOrMedium += $visits;
            [$source, $medium] = self::splitSourceMedium(self::label($row), $fields['source'], $fields['medium']);
            $subtable = $row->getSubtable();
            $namedWithSourceOrMedium += $subtable instanceof DataTable ? self::totalVisits($subtable) : $visits;
            if ($this->isMasked((string) $source) || $this->isMasked((string) $medium) || !$subtable instanceof DataTable) {
                $unchecked += $visits;
                continue;
            }

            $named = 0;
            foreach (self::rows($subtable) as $nameRow) {
                $name = self::label($nameRow);
                $nameVisits = self::visits($nameRow);
                $named += $nameVisits;
                if ($name === '' || $this->isMasked($name)) {
                    $unchecked += $nameVisits;
                    continue;
                }
                $units[] = ['name' => $name, 'source' => $source, 'medium' => $medium, 'visits' => $nameVisits];
            }
            $subtableOthers = self::othersVisits($subtable);
            $unchecked += $subtableOthers;
            $named += $subtableOthers;
            if ($visits > $named) {
                // Marketing Campaigns Reporting leaves visits without a campaign name out of the subtable
                $units[] = ['name' => null, 'source' => $source, 'medium' => $medium, 'visits' => $visits - $named];
            }
        }
        $unchecked += self::othersVisits($sourceMedium);

        // Visits without any value in its reports were tracked before Marketing Campaigns Reporting was activated (it
        // detects a campaign only with at least one value): unknown, not missing values.
        $named = (int) array_sum($fields['name']) + $fieldOthers['name'];
        $known = $withSourceOrMedium + max(0, $named - $namedWithSourceOrMedium);
        $unknown = max(0, $campaignVisits - $known);

        return new CampaignData($campaignVisits, true, $fields, $fieldOthers, $units, $unchecked, $unknown);
    }

    /**
     * "source - medium" as Marketing Campaigns Reporting builds the label; it leaves an empty part out. A single part is
     * the source when the source report knows it and the medium report does not, and the other way round; otherwise it
     * is unknown which one is missing. With more than two parts a value contains " - " itself. Unknown parts are null
     * (such visits are not clean either way).
     *
     * @param array<string, int> $sources values of the source report
     * @param array<string, int> $mediums values of the medium report
     * @return array{0: ?string, 1: ?string}
     */
    private static function splitSourceMedium(string $label, array $sources, array $mediums): array
    {
        $parts = explode(self::SOURCE_MEDIUM_SEPARATOR, $label);
        if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
            return [$parts[0], $parts[1]];
        }
        if (count($parts) === 1 && $label !== '') {
            $isSource = isset($sources[$label]);
            $isMedium = isset($mediums[$label]);
            if ($isSource && !$isMedium) {
                return [$label, null];
            }
            if ($isMedium && !$isSource) {
                return [null, $label];
            }
        }
        return [null, null];
    }

    /**
     * @return array{0: array<string, int>, 1: int} value => visits, and the visits of Others rows and masked values
     */
    private function values(DataTable $table): array
    {
        $values = [];
        $others = self::othersVisits($table);
        foreach (self::rows($table) as $row) {
            $value = self::label($row);
            if ($value === '' || $this->isMasked($value)) {
                $others += self::visits($row);
                continue;
            }
            $values[$value] = ($values[$value] ?? 0) + self::visits($row);
        }
        return [$values, $others];
    }

    /**
     * The rows without the Others row (also when it arrives as a normal row with the label -1).
     *
     * @return list<Row>
     */
    private static function rows(DataTable $table): array
    {
        $rows = [];
        foreach ($table->getRowsWithoutSummaryRow() as $row) {
            if (!self::isOthers($row)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private static function totalVisits(DataTable $table): int
    {
        $visits = 0;
        foreach ($table->getRows() as $row) {
            $visits += self::visits($row);
        }
        return $visits;
    }

    private static function othersVisits(DataTable $table): int
    {
        $visits = 0;
        $summary = $table->getSummaryRow();
        if ($summary instanceof Row) {
            $visits += self::visits($summary);
        }
        foreach ($table->getRowsWithoutSummaryRow() as $row) {
            if (self::isOthers($row)) {
                $visits += self::visits($row);
            }
        }
        return $visits;
    }

    private static function isOthers(Row $row): bool
    {
        $label = $row->getColumn('label');
        return $label === DataTable::LABEL_SUMMARY_ROW || $label === (string) DataTable::LABEL_SUMMARY_ROW;
    }

    private static function label(Row $row): string
    {
        $label = $row->getColumn('label');
        return $label === false || $label === null ? '' : (string) $label;
    }

    private static function visits(Row $row): int
    {
        $visits = $row->getColumn('nb_visits');
        if ($visits === false) {
            $visits = $row->getColumn(\Piwik\Metrics::INDEX_NB_VISITS);
        }
        return (int) $visits;
    }

    private function isMasked(string $value): bool
    {
        return isset($this->masked[$value]);
    }
}
