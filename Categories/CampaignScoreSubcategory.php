<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Categories;

use Piwik\Category\Subcategory;
use Piwik\Piwik;

/**
 * Acquisition > Campaign Score, right after Matomo's Campaigns (order 20) and before the Campaign URL Builder (21).
 */
class CampaignScoreSubcategory extends Subcategory
{
    public const ID = 'FeinwerkCampaignScore_CampaignScore';

    protected $categoryId = 'Referrers_Referrers';
    protected $id = self::ID;
    protected $order = 20.5;

    public function getHelp()
    {
        return '<p>' . Piwik::translate('FeinwerkCampaignScore_ScoreDocumentation') . '</p>'
            . '<p>' . Piwik::translate('FeinwerkCampaignScore_FindingsDocumentation') . '</p>';
    }
}
