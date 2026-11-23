<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Widgets;

use Piwik\Plugins\FeinwerkCampaignScore\Categories\CampaignScoreSubcategory;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\ScoreWidget;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;

/**
 * The score at the top of Acquisition > Campaign Score. The dashboard gets GetOverview instead.
 */
class GetScoreCard extends Widget
{
    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId('Referrers_Referrers');
        $config->setSubcategoryId(CampaignScoreSubcategory::ID);
        $config->setName('FeinwerkCampaignScore_CampaignScore');
        $config->setOrder(1);
        $config->setIsNotWidgetizable();
        $config->setIsWide();
    }

    public function render()
    {
        return ScoreWidget::render(false);
    }
}
