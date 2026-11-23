<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Ui;

use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Evolution;

/**
 * Matomo's evolution graph for "Campaign Score over time" (Reports/GetScore.php), with no point for a period without
 * campaign visits instead of 0 % (Ui/ScoreGaps.php). Everything else is Matomo's own graph, with its id graphEvolution.
 * It is not in the plugin's Visualizations folder, so Matomo never lists it as a visualization of its own (no footer
 * icon, no other report can choose it): the event ViewDataTable.filterViewDataTable puts it in place of Matomo's graph
 * for this report's requests only (FeinwerkCampaignScore::useScoreEvolution(), Ui/ScoreUnit::visualizations()), so
 * widgets and dashboards that ask for graphEvolution keep their ids and get it as well. If a later Matomo lists the
 * visualizations before that event, the report simply shows Matomo's graph again.
 */
class ScoreEvolution extends Evolution
{
    /**
     * Matomo reads these of every visualization outside CoreVisualizations; the same icon and title as its own evolution
     * graph, whose place this one takes
     */
    public const FOOTER_ICON = 'icon-evolution';
    public const FOOTER_ICON_TITLE = 'General_EvolutionOverPeriod';

    public function beforeRender()
    {
        parent::beforeRender();
        try {
            if (is_array($this->config->footer_icons)) {
                $this->config->footer_icons = ScoreGaps::footerIcons($this->config->footer_icons, static::getViewDataTableId());
            }
        } catch (\Throwable $e) {
            // Matomo's footer stays
        }
    }

    public function getGraphData($dataTable, $properties)
    {
        $graph = parent::getGraphData($dataTable, $properties);
        try {
            if ($this->isComparing() || !is_array($graph)) {
                return $graph;
            }

            $label = $properties['translations'][ScoreGaps::COLUMN] ?? null;

            return ScoreGaps::apply($graph, ScoreGaps::missing($dataTable), is_string($label) ? $label : null);
        } catch (\Throwable $e) {
            return $graph;
        }
    }
}
