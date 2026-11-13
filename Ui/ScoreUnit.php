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
        if ($column !== 'score') {
            return null;
        }
        foreach ([['module', 'action'], ['apiModule', 'apiAction'], ['moduleToWidgetize', 'actionToWidgetize']] as [$module, $action]) {
            if (($request[$module] ?? '') === self::PLUGIN && ($request[$action] ?? '') === self::ACTION) {
                return '%';
            }
        }
        return null;
    }
}
