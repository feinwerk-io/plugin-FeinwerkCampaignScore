<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Widgets;

use Piwik\Plugins\FeinwerkCampaignScore\Ui\ScoreWidget;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;

/**
 * Dashboard widget (Acquisition): the score, the three largest problems and a link to the report.
 */
class GetOverview extends Widget
{
    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId('Referrers_Referrers');
        $config->setName('FeinwerkCampaignScore_CampaignScore');
        $config->setOrder(99);
    }

    public function render()
    {
        return ScoreWidget::render(true);
    }
}
