<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Data;

use Piwik\Plugins\FeinwerkCampaignScore\Check\Finding;

/**
 * The segment of a finding: the campaign visits with the values to fix (or without a value), for Matomo's segmented
 * visitor log. Values are URL-encoded like the segments of Matomo's own reports (AddSegmentByLabel).
 */
final class SegmentBuilder
{
    /** at most this many values in one OR list */
    public const MAX_VALUES = 20;

    /** field => segment with Marketing Campaigns Reporting */
    private const MCR = [
        'name' => 'campaignName',
        'keyword' => 'campaignKeyword',
        'source' => 'campaignSource',
        'medium' => 'campaignMedium',
        'content' => 'campaignContent',
        'group' => 'campaignGroup',
        'placement' => 'campaignPlacement',
    ];

    /** field => segment of Matomo's Referrers plugin (without Marketing Campaigns Reporting) */
    private const CORE = [
        'name' => 'referrerName',
        'keyword' => 'referrerKeyword',
    ];

    public static function forFinding(Finding $finding, bool $withSourceMedium): ?string
    {
        $dimensions = $withSourceMedium ? self::MCR : self::CORE;
        $dimension = $dimensions[$finding->field] ?? null;
        if ($dimension === null) {
            return null;
        }

        if ($finding->type === Finding::INCOMPLETE) {
            $conditions = [$dimension . '=='];
        } else {
            $conditions = [];
            foreach (array_slice($finding->valuesToFix(), 0, self::MAX_VALUES) as $value) {
                $conditions[] = $dimension . '==' . urlencode($value);
            }
            if ($conditions === []) {
                return null;
            }
        }

        return 'referrerType==campaign;' . implode(',', $conditions);
    }
}
