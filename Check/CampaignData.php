<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Check;

/**
 * The campaign values of one website and period, read from Matomo's reports.
 */
final class CampaignData
{
    /**
     * @param int $campaignVisits all visits with the referrer type campaign
     * @param bool $withSourceMedium true when source and medium are known (Marketing Campaigns Reporting)
     * @param array<string, array<string|int, int>> $fields field (name, keyword, source, medium) => value => visits,
     *                                                       without the "Others" rows
     * @param array<string, int> $fieldOthers field => visits in its "Others" rows (values unknown, but present)
     * @param list<array{name: ?string, source: ?string, medium: ?string, visits: int}> $units visits with the same
     *        combination of the scored fields; null = no value
     * @param int $uncheckedVisits visits whose combination is unknown because the report put them into "Others"
     * @param int $unknownVisits visits without any value in the checked fields' reports (with Marketing Campaigns
     *                           Reporting: tracked before it was activated); not checked and no missing values
     */
    public function __construct(
        public readonly int $campaignVisits,
        public readonly bool $withSourceMedium,
        public readonly array $fields,
        public readonly array $fieldOthers,
        public readonly array $units,
        public readonly int $uncheckedVisits,
        public readonly int $unknownVisits = 0
    ) {
    }
}
