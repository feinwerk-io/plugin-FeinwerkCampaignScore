<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Data\AllSites;

/**
 * The overview of all websites on large instances (load test 2026-11-13): which period, which websites, which cache.
 *
 * @group FeinwerkCampaignScore
 */
class AllSitesTest extends TestCase
{
    public function test_periodProblem_aSinglePeriodIsFine(): void
    {
        $this->assertNull(AllSites::periodProblem('day', 'yesterday'));
        $this->assertNull(AllSites::periodProblem('month', '2026-11-05'));
    }

    /** Matomo would compute a date range on the fly for every website. */
    public function test_periodProblem_aDateRangeIsRefused(): void
    {
        $this->assertSame(AllSites::RANGE, AllSites::periodProblem('range', '2026-11-01,2026-11-05'));
        $this->assertSame(AllSites::RANGE, AllSites::periodProblem('range', 'last7'));
    }

    public function test_periodProblem_severalPeriodsAreRefused(): void
    {
        $this->assertSame(AllSites::SEVERAL, AllSites::periodProblem('day', '2026-11-01,2026-11-05'));
        $this->assertSame(AllSites::SEVERAL, AllSites::periodProblem('week', 'last8'));
        $this->assertSame(AllSites::SEVERAL, AllSites::periodProblem('month', 'previous3'));
    }

    public function test_pick_keepsAllWebsitesUpToTheLimit(): void
    {
        $this->assertSame([3, 1, 2], AllSites::pick([3, 1, 2], [], 3));
    }

    public function test_pick_takesTheWebsitesWithTheMostVisits(): void
    {
        $picked = AllSites::pick([1, 2, 3, 4, 5], [1 => 10, 2 => 500, 3 => 0, 4 => 500, 5 => 20], 3);

        $this->assertSame([2, 4, 5], $picked, 'most visits first, a tie by website ID');
    }

    public function test_pick_websitesWithoutVisitsComeLastByID(): void
    {
        $this->assertSame([9, 2, 7], AllSites::pick([7, 2, 9, 11], [9 => 5], 3));
    }

    public function test_groups_ofTenWebsites(): void
    {
        $groups = AllSites::groups(range(1, 25));

        $this->assertSame([10, 10, 5], array_map('count', $groups));
        $this->assertSame([21, 22, 23, 24, 25], $groups[2]);
    }

    /**
     * Load test 2026-11-13: Matomo's process died reading 25 large websites at once; ten with five very large ones took
     * 255 MB. A group is bounded by its campaign names, so a very large website is read alone.
     */
    public function test_batches_areBoundedByTheCampaignNames(): void
    {
        $rows = [1 => 400, 2 => 1000, 3 => 1000, 4 => 400, 5 => 400, 6 => 2500, 7 => 10, 8 => 10];

        $this->assertSame(
            [[1, 2], [3, 4, 5], [6], [7, 8]],
            AllSites::batches([1, 2, 3, 4, 5, 6, 7, 8], $rows, 2000, 10)
        );
    }

    public function test_batches_holdAtMostTheGroupSize(): void
    {
        $this->assertSame([[1, 2, 3], [4, 5]], AllSites::batches([1, 2, 3, 4, 5], [], 2000, 3));
    }

    public function test_batches_ofNoWebsites(): void
    {
        $this->assertSame([], AllSites::batches([], [], 2000, 10));
    }

    public function test_cacheKey_isStableAndUsableAsCacheId(): void
    {
        $key = AllSites::cacheKey('admin', [1, 2], 'day', 'yesterday', '', '2026-11-13', 'abc');

        $this->assertSame($key, AllSites::cacheKey('admin', [1, 2], 'day', 'yesterday', '', '2026-11-13', 'abc'));
        $this->assertMatchesRegularExpression('/^fwcs_allsites_[0-9a-f]{32}$/', $key);
    }

    public function test_cacheKey_changesWithEveryInput(): void
    {
        $base = ['admin', [1, 2], 'day', 'yesterday', '', '2026-11-13', 'abc'];
        $key = AllSites::cacheKey(...$base);
        $changes = [
            0 => 'other-user',
            1 => [1, 3],
            2 => 'week',
            3 => 'today',
            4 => 'browserCode==FF',
            // "yesterday" is another day after midnight
            5 => '2026-11-14',
            // settings changed (an accepted value, a medium list)
            6 => 'abd',
        ];
        foreach ($changes as $index => $value) {
            $changed = $base;
            $changed[$index] = $value;
            $this->assertNotSame($key, AllSites::cacheKey(...$changed), 'input ' . $index);
        }
    }
}
