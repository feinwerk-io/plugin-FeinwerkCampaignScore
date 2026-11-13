<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Data\PeriodGroups;

/**
 * Several periods (score over time, the small line) are read a few at a time (load test 2026-11-13: 30 days at once
 * took 307 MB on a website with 3,200 campaign visits a day). Uses Matomo's period classes (no database).
 *
 * @group FeinwerkCampaignScore
 */
class PeriodGroupsTest extends TestCase
{
    public function test_isSeveral(): void
    {
        $this->assertTrue(PeriodGroups::isSeveral('day', '2026-10-07,2026-11-05'));
        $this->assertTrue(PeriodGroups::isSeveral('week', 'last8'));
        $this->assertTrue(PeriodGroups::isSeveral('month', 'previous3'));
        $this->assertFalse(PeriodGroups::isSeveral('day', '2026-11-05'));
        $this->assertFalse(PeriodGroups::isSeveral('range', '2026-11-01,2026-11-05'), 'a date range is one period');
    }

    public function test_dates_ofThirtyDaysInGroupsOfSeven(): void
    {
        $days = [];
        for ($day = new \DateTimeImmutable('2026-10-07'); $day <= new \DateTimeImmutable('2026-11-05'); $day = $day->modify('+1 day')) {
            $days[] = $day->format('Y-m-d');
        }

        $this->assertSame([
            '2026-10-07,2026-10-13',
            '2026-10-14,2026-10-20',
            '2026-10-21,2026-10-27',
            '2026-10-28,2026-11-03',
            '2026-11-04,2026-11-05',
        ], PeriodGroups::dates($days, 7));
    }

    public function test_dates_ofWeeksUseTheFirstDayOfEachWeek(): void
    {
        $weeks = ['2026-09-14', '2026-09-21', '2026-09-28', '2026-10-05', '2026-10-12', '2026-10-19', '2026-10-26', '2026-11-02'];

        $this->assertSame(['2026-09-14,2026-10-12', '2026-10-19,2026-11-02'], PeriodGroups::dates($weeks, 5));
    }

    public function test_dates_aGroupOfOneKeepsTheCommaForOneMapOfMatomo(): void
    {
        $this->assertSame(['2026-11-05,2026-11-05'], PeriodGroups::dates(['2026-11-05'], 7));
        $this->assertSame([], PeriodGroups::dates([], 7));
    }
}
