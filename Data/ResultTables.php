<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Data;

use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Finding;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Result;

/**
 * The tables of the API methods getScore and getFindings.
 */
final class ResultTables
{
    /** One row: the score in percent (left out when there is none) and its basis. */
    /**
     * @param bool $withFindings false for several periods: they are read for the score only (findings of campaign
     *        name, source and medium alone would be a misleading count)
     */
    public static function score(Result $result, bool $withFindings = true): DataTable
    {
        $columns = [];
        if ($result->score !== null) {
            $columns['score'] = round($result->score * 100, 1);
        }
        if ($result->previousScore !== null) {
            $columns['previous_score'] = round($result->previousScore * 100, 1);
        }
        $columns += [
            'clean_visits' => $result->cleanVisits,
            'checked_visits' => $result->checkedVisits,
            'campaign_visits' => $result->campaignVisits,
            'unchecked_visits' => $result->uncheckedVisits,
            'with_source_medium' => (int) $result->withSourceMedium,
        ];
        // the clean share of each scored field (percent): where the score is lost
        foreach ($result->fieldShares as $field => $share) {
            if ($share !== null) {
                $columns['score_' . $field] = round($share * 100, 1);
            }
        }
        if ($withFindings) {
            $columns['findings'] = count($result->findings);
            if ($result->fixedFindings !== null) {
                $columns['fixed_findings'] = $result->fixedFindings;
            }
        }

        $table = new DataTable();
        $table->addRow(new Row([Row::COLUMNS => $columns]));
        return $table;
    }

    /**
     * One row per finding, most visits first. Columns label (the value, the variants separated by ", ", empty for a
     * missing value), nb_visits and score_gain (the score points the finding costs); metadata type, field, suggestion, values (value => visits), contexts (where the
     * values occur), new (1: not in the previous period) and segment (the visits concerned).
     */
    public static function findings(Result $result): DataTable
    {
        $table = new DataTable();
        foreach ($result->findings as $finding) {
            $values = [];
            foreach ($finding->values as $value => $visits) {
                $values[(string) $value] = $visits;
            }
            // the texts are metadata: Matomo sums every column of a table for its totals row
            $table->addRow(new Row([
                Row::COLUMNS => [
                    'label' => implode(', ', array_keys($values)),
                    'nb_visits' => $finding->visits,
                    // the score points the finding costs: its share of the unclean visits (0 for typo hints and for
                    // fields that are not scored)
                    'score_gain' => $finding->scoreGain,
                ],
                Row::METADATA => array_filter([
                    'type' => $finding->type,
                    'field' => $finding->field,
                    'suggestion' => (string) $finding->suggestion,
                    'values' => $finding->values,
                    'contexts' => $finding->contexts,
                    'new' => (int) $finding->isNew,
                    // the values the row action "accept" takes over (none for a missing value)
                    'accept' => $finding->type === Finding::INCOMPLETE ? [] : $finding->valuesToFix(),
                    // Matomo's segmented visitor log offers itself for rows with this metadata
                    'segment' => SegmentBuilder::forFinding($finding, $result->withSourceMedium),
                ], static function ($value): bool {
                    return $value !== null;
                }),
            ]));
        }
        return $table;
    }

    /**
     * The correction list: one row per value to fix, with its field, problem and suggestion (for the CSV download).
     */
    public static function corrections(Result $result): DataTable
    {
        $table = new DataTable();
        foreach ($result->findings as $finding) {
            foreach ($finding->valuesToFix() as $value) {
                $table->addRow(new Row([
                    Row::COLUMNS => ['label' => $value, 'nb_visits' => (int) ($finding->values[$value] ?? 0)],
                    Row::METADATA => [
                        'field' => $finding->field,
                        'type' => $finding->type,
                        'suggestion' => (string) $finding->suggestion,
                    ],
                ]));
            }
        }
        return $table;
    }

    /**
     * @param array<string, array{metadata: array<string, mixed>, result: Result}> $periods keyed by period label, ''
     *        for a single period
     * @param callable(Result): DataTable $build
     * @return DataTable|DataTable\Map
     */
    public static function output(array $periods, callable $build)
    {
        $tables = [];
        foreach ($periods as $label => $period) {
            $table = $build($period['result']);
            $table->setAllTableMetadata($period['metadata'] + $table->getAllTableMetadata());
            $tables[(string) $label] = $table;
        }

        if (array_keys($tables) === ['']) {
            return $tables[''];
        }
        $map = new DataTable\Map();
        $map->setKeyName('date');
        foreach ($tables as $label => $table) {
            $map->addTable($table, $label);
        }
        return $map;
    }
}
