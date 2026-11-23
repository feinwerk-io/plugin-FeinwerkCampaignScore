<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Columns;

use Piwik\Columns\Dimension;

/**
 * The dimension of the findings report: the campaign value (or the spelling variants) a finding is about. Only for
 * the report; it has no column in the log tables and no segment.
 */
class FindingValue extends Dimension
{
    protected $type = self::TYPE_TEXT;
    protected $nameSingular = 'FeinwerkCampaignScore_ColumnValue';
}
