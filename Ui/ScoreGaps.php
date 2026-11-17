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
 * Campaign Score over time: a period without campaign visits has no score, so the graph shows no point there instead of
 * 0 % (Peter, 2026-11-14: "Monate ohne Besuche als Lücke zeigen"). Matomo's evolution graph turns a missing value into 0;
 * Ui/ScoreEvolution.php hands its graph data through here. The graph library leaves a null point out and
 * keeps the others in place: nothing before the first period with a score, and a line from one score to the next
 * across periods without one (its option breakOnNull would draw such points at the top in Matomo's graph). Pure.
 */
final class ScoreGaps
{
    /** the column of the report */
    public const COLUMN = 'score';

    /**
     * Which points of the graph have no score: one entry per period of the Map, in its order.
     *
     * @param DataTable|DataTable\Map $table
     * @return list<bool>
     */
    public static function missing($table): array
    {
        if (!$table instanceof DataTable\Map) {
            return [];
        }
        $missing = [];
        foreach ($table->getDataTables() as $period) {
            $row = $period instanceof DataTable ? $period->getFirstRow() : false;
            $score = $row ? $row->getColumn(self::COLUMN) : false;
            $missing[] = !is_numeric($score);
        }

        return $missing;
    }

    /**
     * The graph data of Matomo's evolution graph (params.series, data: one list of values per series) with null in the
     * score series where a period has no score. Unchanged when the points do not match.
     *
     * @param string|null $scoreLabel the score series' label (the column's translation, as Matomo names the series);
     *        null: only a graph with a single series is changed
     *
     * @param array<string, mixed> $graph
     * @param list<bool> $missing
     * @return array<string, mixed>
     */
    public static function apply(array $graph, array $missing, ?string $scoreLabel = null): array
    {
        if (!in_array(true, $missing, true) || !isset($graph['data']) || !is_array($graph['data'])) {
            return $graph;
        }
        foreach ($graph['data'] as $index => $values) {
            if (!is_array($values) || count($values) !== count($missing)) {
                return $graph;
            }
        }
        foreach ($graph['data'] as $index => $values) {
            // only the score: another series (none today, the report shows one column) keeps its real zeros. Matomo names
            // a series by the column's translation; without it, a single series is the score
            $label = $graph['params']['series'][$index]['internalLabel'] ?? null;
            $isScore = $scoreLabel !== null && $scoreLabel !== '' ? $label === $scoreLabel : count($graph['data']) === 1;
            if (!$isScore) {
                continue;
            }
            foreach ($missing as $point => $isMissing) {
                if ($isMissing) {
                    $graph['data'][$index][$point] = null;
                }
            }
        }

        return $graph;
    }

    /**
     * The footer icons of the graph without the extra button Matomo adds because this graph is not one of its own
     * (Manager::configureFooterIcons() lists a view from outside CoreVisualizations as a choice of itself): Matomo's
     * evolution graph has no such button, so the footer stays as Matomo shows it. Other buttons and groups stay.
     *
     * @param array<int, mixed> $groups the view's footer_icons
     * @return array<int, mixed>
     */
    public static function footerIcons(array $groups, string $viewId): array
    {
        $out = [];
        foreach ($groups as $group) {
            if (is_array($group) && ($group['class'] ?? '') === 'tableGraphViews' && isset($group['buttons']) && is_array($group['buttons'])) {
                $group['buttons'] = array_values(array_filter($group['buttons'], static function ($button) use ($viewId) {
                    return !is_array($button) || ($button['id'] ?? null) !== $viewId;
                }));
                if ($group['buttons'] === []) {
                    continue;
                }
            }
            $out[] = $group;
        }

        return $out;
    }
}
