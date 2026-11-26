<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Ui;

use Piwik\DataTable;

/**
 * The alert on a score drop inside Matomo's email report (Peter, 2026-11-25, question dialog: "In the email report
 * (Recommended)"): when the score of the period fell by DROP_POINTS or more against the period before, the score's
 * section moves to the top of the email and its heading says so, with the number of new findings; new findings are
 * marked in the findings list. No mail of its own, no setting: only people who chose the Campaign Score email get it.
 * Called from the event ScheduledReports.processReports. Pure apart from the tables it is given.
 */
final class ScoreAlert
{
    /** from this drop on (points, compared like the score card: whole points) the email leads with the alert */
    public const DROP_POINTS = 5;

    /**
     * The alert heading, or null without a drop of DROP_POINTS or more (or without a previous score).
     *
     * @param callable(string, array=): string $translate like Piwik::translate(string $key, array $args = [])
     * @param callable(int|float): string $formatPercent
     * @param callable(int|float): string $formatNumber
     */
    public static function heading(?float $score, ?float $previous, int $newFindings, callable $translate, callable $formatPercent, callable $formatNumber): ?string
    {
        if ($score === null || $previous === null) {
            return null;
        }
        $drop = (int) round($previous) - (int) round($score);
        if ($drop < self::DROP_POINTS) {
            return null;
        }
        $heading = $translate('FeinwerkCampaignScore_AlertDrop', [
            $formatNumber($drop),
            $formatPercent((int) round($previous)),
            $formatPercent((int) round($score)),
        ]);
        if ($newFindings > 0) {
            $heading .= ' ' . ($newFindings === 1
                ? $translate('FeinwerkCampaignScore_AlertNewOne')
                : $translate('FeinwerkCampaignScore_AlertNewMany', [$formatNumber($newFindings)]));
        }
        return $heading;
    }

    /**
     * Changes the email's processed reports: the alert heading on the score's section, moved to the top; new findings
     * marked. Only the plugin's own sections are touched, the order of the others is kept.
     *
     * @param array<int, array<string, mixed>> $processedReports entries of API.getProcessedReport
     * @param callable(string, array=): string $translate
     * @param callable(int|float): string $formatPercent
     * @param callable(int|float): string $formatNumber
     * @return array<int, array<string, mixed>>
     */
    public static function apply(array $processedReports, callable $translate, callable $formatPercent, callable $formatNumber): array
    {
        $scoreIndex = null;
        $newFindings = 0;
        foreach ($processedReports as $index => $processed) {
            $metadata = $processed['metadata'] ?? [];
            if (($metadata['module'] ?? '') !== 'FeinwerkCampaignScore') {
                continue;
            }
            if (($metadata['action'] ?? '') === 'getScore') {
                $scoreIndex = $index;
            }
            if (($metadata['action'] ?? '') === 'getFindings') {
                $newFindings = self::markNew($processed, $translate('FeinwerkCampaignScore_NewBadge'));
            }
        }
        if ($scoreIndex === null) {
            return $processedReports;
        }
        $row = ($processedReports[$scoreIndex]['reportData'] ?? null) instanceof DataTable
            ? $processedReports[$scoreIndex]['reportData']->getFirstRow()
            : false;
        $number = static function ($value): ?float {
            return is_numeric($value) ? (float) $value : null;
        };
        // Matomo's processed reports show a missing metric as 0: without checked visits there is no score (not 0 %),
        // and a previous score of 0 is a missing one
        $checked = $row ? (int) $number($row->getColumn('checked_visits')) : 0;
        $score = $checked > 0 ? $number($row->getColumn('score')) : null;
        $previous = $row ? $number($row->getColumn('previous_score')) : null;
        if (!$previous) {
            $previous = null;
            unset($processedReports[$scoreIndex]['columns']['previous_score']);
        }
        $heading = self::heading($score, $previous, $newFindings, $translate, $formatPercent, $formatNumber);
        if ($heading === null) {
            return $processedReports;
        }
        $section = $processedReports[$scoreIndex];
        $section['metadata']['name'] = $heading;
        unset($processedReports[$scoreIndex]);

        return array_merge([$section], array_values($processedReports));
    }

    /**
     * Puts "new" before the label of every finding that was not in the previous period; returns how many.
     *
     * @param array<string, mixed> $processed
     */
    private static function markNew(array $processed, string $badge): int
    {
        $data = $processed['reportData'] ?? null;
        $metadata = $processed['reportMetadata'] ?? null;
        if (!$data instanceof DataTable || !$metadata instanceof DataTable) {
            return 0;
        }
        // Matomo builds the metadata table row by row with the data: the same position is the same finding
        $rows = array_values($data->getRows());
        $marked = 0;
        foreach (array_values($metadata->getRows()) as $position => $row) {
            if ((int) $row->getColumn('new') === 1 && isset($rows[$position])) {
                $rows[$position]->setColumn('label', $badge . ': ' . (string) $rows[$position]->getColumn('label'));
                $marked++;
            }
        }
        return $marked;
    }
}
