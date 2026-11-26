<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Check;

/**
 * The score and the findings of one website and period.
 */
final class Result
{
    /**
     * @param float|null $score share of the checked campaign visits with clean values (0 to 1), null without any
     * @param list<Finding> $findings most visits first
     * @param float|null $previousScore the score of the previous period, when it was analyzed (0 to 1)
     * @param array<string, float|null> $fieldShares scored field (name, source, medium) => share of its visits with a
     *        clean value (0 to 1), null without visits; only the scored fields
     * @param int|null $fixedFindings findings of the previous period that are fixed now (the clean spelling is in use,
     *        or a missing value is filled); null without a previous period to compare with
     */
    public function __construct(
        public readonly ?float $score,
        public readonly int $cleanVisits,
        public readonly int $checkedVisits,
        public readonly int $campaignVisits,
        public readonly int $uncheckedVisits,
        public readonly bool $withSourceMedium,
        public readonly array $findings,
        public readonly ?float $previousScore = null,
        public readonly array $fieldShares = [],
        public readonly ?int $fixedFindings = null
    ) {
    }
}
