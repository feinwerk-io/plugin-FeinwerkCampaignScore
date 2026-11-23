<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Data\PreviousPeriod;

/**
 * The period before the selected one, for the comparison in the score card and the "new" findings.
 *
 * @group FeinwerkCampaignScore
 */
class PreviousPeriodTest extends TestCase
{
    public function test_dayWeekMonthYear_theSamePeriodOneStepEarlier(): void
    {
        $this->assertSame(['period' => 'day', 'date' => '2026-11-04'], PreviousPeriod::of('day', '2026-11-05', 'UTC'));
        $this->assertSame(['period' => 'week', 'date' => '2026-10-29'], PreviousPeriod::of('week', '2026-11-05', 'UTC'));
        $this->assertSame(['period' => 'year', 'date' => '2025-06-01'], PreviousPeriod::of('year', '2026-06-01', 'UTC'));
        $month = PreviousPeriod::of('month', '2026-03-31', 'UTC');
        $this->assertSame('month', $month['period']);
        $this->assertStringStartsWith('2026-02', $month['date']);
    }

    public function test_range_theSameNumberOfDaysRightBefore(): void
    {
        $this->assertSame(['period' => 'range', 'date' => '2026-10-27,2026-10-31'], PreviousPeriod::of('range', '2026-11-01,2026-11-05', 'UTC'));
    }

    public function test_severalPeriodsOrUnknownInput_haveNoPreviousPeriod(): void
    {
        $this->assertNull(PreviousPeriod::of('day', 'last7', 'UTC'));
        $this->assertNull(PreviousPeriod::of('day', '2026-11-01,2026-11-05', 'UTC'));
        $this->assertNull(PreviousPeriod::of('decade', '2026-11-01', 'UTC'));
        $this->assertNull(PreviousPeriod::of('day', 'not a date', 'UTC'));
    }

    public function test_keywords_areResolvedInTheTimezoneOfTheWebsite(): void
    {
        $previous = PreviousPeriod::of('day', 'yesterday', 'Europe/Berlin');

        $this->assertSame('day', $previous['period']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $previous['date']);
        $this->assertSame(PreviousPeriod::of('range', 'last7', 'UTC')['period'], 'range');
    }
}
