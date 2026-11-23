<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Ui;

/**
 * The unit of the score in evolution graphs (event Metrics.getEvolutionUnit): a percentage. Matomo 6 forecasts the open
 * period of an evolution graph and treats a column without unit like a sum; with "%" it averages instead. Only for the
 * plugin's own report, because "score" is a generic column name. Pure.
 */
final class ScoreUnit
{
    private const PLUGIN = 'FeinwerkCampaignScore';
    private const ACTION = 'getScore';

    /**
     * @param array<string, mixed> $request the request's module, action, apiModule, apiAction, moduleToWidgetize and
     *        actionToWidgetize (missing: none)
     */
    public static function forColumn(string $column, array $request): ?string
    {
        return $column === 'score' && self::isScoreRequest($request) ? '%' : null;
    }

    /**
     * Whether the request is for the plugin's own report "Campaign Score over time" (directly, through the API or as a
     * Widgetize embed).
     *
     * @param array<string, mixed> $request the request's module, action, apiModule, apiAction, moduleToWidgetize and
     *        actionToWidgetize (missing: none)
     */
    public static function isScoreRequest(array $request): bool
    {
        foreach ([['module', 'action'], ['apiModule', 'apiAction'], ['moduleToWidgetize', 'actionToWidgetize']] as [$module, $action]) {
            if (($request[$module] ?? '') === self::PLUGIN && ($request[$action] ?? '') === self::ACTION) {
                return true;
            }
        }
        return false;
    }

    /**
     * The visualizations of the request (event ViewDataTable.filterViewDataTable): for "Campaign Score over time"
     * Matomo's evolution graph is the one without a point for periods without campaign visits (Ui/ScoreEvolution.php),
     * so the widgets and saved views that ask for graphEvolution get it too. Other reports keep Matomo's graph.
     *
     * @param array<string, string> $visualizations visualization id => class
     * @param array<string, mixed> $request as for isScoreRequest()
     * @return array<string, string>
     */
    public static function visualizations(array $visualizations, array $request, string $evolutionId, string $scoreClass): array
    {
        if (isset($visualizations[$evolutionId]) && self::isScoreRequest($request)) {
            $visualizations[$evolutionId] = $scoreClass;
        }
        return $visualizations;
    }
}
