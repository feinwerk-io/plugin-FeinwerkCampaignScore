<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Ui;

use PHPUnit\Framework\TestCase;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\ScoreAlert;

/**
 * The score drop in Matomo's email report (Peter, 2026-11-25: "In the email report (Recommended)").
 *
 * @group FeinwerkCampaignScore
 */
class ScoreAlertTest extends TestCase
{
    /** @param array<int, string> $args */
    public static function translate(string $key, array $args = []): string
    {
        return '[' . $key . ($args === [] ? '' : ':' . implode('|', $args)) . ']';
    }

    private static function percent(): callable
    {
        return static function ($value): string {
            return $value . '%';
        };
    }

    private static function number(): callable
    {
        return static function ($value): string {
            return (string) $value;
        };
    }

    private static function table(array $rows): DataTable
    {
        $table = new DataTable();
        foreach ($rows as $columns) {
            $table->addRow(new Row([Row::COLUMNS => $columns]));
        }
        return $table;
    }

    /** @return array<int, array<string, mixed>> an email with another report first, then the score and the findings */
    private static function email(float $score, ?float $previous): array
    {
        // as Matomo's processed report gives it: a missing metric is 0
        $scoreRow = ['score' => $score, 'previous_score' => $previous ?? 0, 'checked_visits' => 120];
        return [
            ['metadata' => ['module' => 'VisitsSummary', 'action' => 'get', 'name' => 'Visits'], 'reportData' => self::table([['nb_visits' => 9]])],
            ['metadata' => ['module' => 'FeinwerkCampaignScore', 'action' => 'getScore', 'name' => 'Campaign Score'], 'reportData' => self::table([$scoreRow]),
                'columns' => ['score' => 'Campaign Score', 'previous_score' => 'Previous period', 'checked_visits' => 'Checked']],
            [
                'metadata' => ['module' => 'FeinwerkCampaignScore', 'action' => 'getFindings', 'name' => 'Findings'],
                'reportData' => self::table([['label' => 'Black Friday'], ['label' => 'fb'], ['label' => 'e-mail']]),
                'reportMetadata' => self::table([['new' => 1], ['new' => 0], ['new' => 1]]),
            ],
        ];
    }

    public function test_a_drop_of_five_points_leads_the_email_with_the_new_findings(): void
    {
        $email = ScoreAlert::apply(self::email(81.2, 88.4), [self::class, 'translate'], self::percent(), self::number());

        $this->assertSame('[FeinwerkCampaignScore_AlertDrop:7|88%|81%] [FeinwerkCampaignScore_AlertNewMany:2]', $email[0]['metadata']['name']);
        $this->assertSame('Visits', $email[1]['metadata']['name'], 'the other sections keep their order');
        $labels = array_map(static function (Row $row): string {
            return (string) $row->getColumn('label');
        }, $email[2]['reportData']->getRows());
        $this->assertSame(['[FeinwerkCampaignScore_NewBadge]: Black Friday', 'fb', '[FeinwerkCampaignScore_NewBadge]: e-mail'], array_values($labels));
    }

    public function test_less_than_five_points_or_no_previous_period_change_only_the_marks(): void
    {
        foreach ([[84.0, 88.4], [90.0, 80.0], [80.0, null]] as [$score, $previous]) {
            $email = ScoreAlert::apply(self::email($score, $previous), [self::class, 'translate'], self::percent(), self::number());
            $this->assertSame('Visits', $email[0]['metadata']['name']);
            $this->assertSame('Campaign Score', $email[1]['metadata']['name']);
        }
        $this->assertNull(ScoreAlert::heading(null, 90.0, 0, [self::class, 'translate'], self::percent(), self::number()));
        $this->assertSame('[FeinwerkCampaignScore_AlertDrop:5|90%|85%] [FeinwerkCampaignScore_AlertNewOne]',
            ScoreAlert::heading(85.0, 90.0, 1, [self::class, 'translate'], self::percent(), self::number()));
    }

    public function test_an_email_without_the_score_stays_as_it_is(): void
    {
        $email = [['metadata' => ['module' => 'VisitsSummary', 'action' => 'get', 'name' => 'Visits'], 'reportData' => self::table([['nb_visits' => 9]])]];
        $this->assertSame($email, ScoreAlert::apply($email, [self::class, 'translate'], self::percent(), self::number()));
    }

    /** Code review 2026-11-26: Matomo fills a missing score with 0; without checked visits that is no score, no drop. */
    public function test_a_period_without_a_score_is_no_drop(): void
    {
        $email = self::email(0.0, 85.0);
        $email[1]['reportData']->getFirstRow()->setColumn('checked_visits', 0);
        $email = ScoreAlert::apply($email, [self::class, 'translate'], self::percent(), self::number());

        $this->assertSame('Visits', $email[0]['metadata']['name']);
        $this->assertSame('Campaign Score', $email[1]['metadata']['name']);
    }

    public function test_a_missing_previous_score_is_not_shown_as_zero(): void
    {
        $email = ScoreAlert::apply(self::email(80.0, null), [self::class, 'translate'], self::percent(), self::number());
        $this->assertArrayNotHasKey('previous_score', $email[1]['columns']);

        $email = ScoreAlert::apply(self::email(80.0, 78.0), [self::class, 'translate'], self::percent(), self::number());
        $this->assertArrayHasKey('previous_score', $email[1]['columns']);
    }
}
