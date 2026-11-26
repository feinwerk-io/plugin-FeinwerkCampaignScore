<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Ui;

/**
 * The Campaign Score email report of the current user (UI review proposal e, Peter 2026-11-25 "alle"): one click in the
 * score card creates a Matomo email report with the score and the findings, sent every week (API addEmailReport).
 * Matomo's own email reports (plugin ScheduledReports) send it; this class only knows its parts. Pure.
 */
final class EmailReport
{
    /** the reports of the email, as Matomo's report metadata names them (module_action) */
    public const REPORTS = ['FeinwerkCampaignScore_getScore', 'FeinwerkCampaignScore_getFindings'];
    /** tables only, graphs only for key metrics (Matomo's default in its form) */
    public const DISPLAY_FORMAT = 1;
    /** sent at this hour (UTC), early in the morning in Europe */
    public const HOUR = 5;
    public const PERIODS = ['day', 'week', 'month'];

    /**
     * The user's existing Campaign Score email report among Matomo's email reports of the website: the first of the
     * user that contains the score; null when there is none.
     *
     * @param list<array<string, mixed>> $reports rows of ScheduledReports.getReports (reports decoded to a list)
     * @return array<string, mixed>|null
     */
    public static function existing(array $reports, string $login): ?array
    {
        foreach ($reports as $report) {
            if ((string) ($report['login'] ?? '') !== $login) {
                continue;
            }
            $ids = $report['reports'] ?? [];
            if (is_string($ids)) {
                $ids = json_decode($ids, true);
            }
            if (is_array($ids) && in_array(self::REPORTS[0], $ids, true)) {
                return $report;
            }
        }
        return null;
    }

    /**
     * The parameters of ScheduledReports.addReport for the website and period.
     *
     * @return array<string, mixed>
     */
    public static function addReportParameters(int $idSite, string $period, string $description): array
    {
        return [
            'idSite' => $idSite,
            'description' => $description,
            'period' => $period,
            'hour' => self::HOUR,
            'reportType' => 'email',
            'reportFormat' => 'html',
            'reports' => self::REPORTS,
            'parameters' => [
                'displayFormat' => self::DISPLAY_FORMAT,
                'emailMe' => true,
                'evolutionGraph' => false,
                'additionalEmails' => [],
            ],
        ];
    }

    /** Matomo's page of the email reports for the website. */
    public static function manageUrl(int $idSite): string
    {
        return 'index.php?module=ScheduledReports&action=index&idSite=' . $idSite . '&period=day&date=yesterday';
    }
}
