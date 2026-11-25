<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Ui;

/**
 * One line under Matomo's campaign names report (Referrers.getCampaigns, or Marketing Campaigns Reporting's
 * getName; through Matomo's hook Template.afterCampaignsReport): the Campaign Score of the period with its findings and a link to them (Peter, 2026-11-24, design D4 of the
 * UI review: "D4 ja"). For everyone with view access, not a promotion; read from the archived reports like the report
 * itself; only for a single period and only in the HTML table of the report (not in its subtables or exports). Pure.
 */
final class CampaignsFooter
{
    /**
     * Whether the line belongs to this request: Matomo's hook Template.afterCampaignsReport runs only for the campaign
     * names report (core's Referrers.getCampaigns, or Marketing Campaigns Reporting's getName) and not for its subtables;
     * the line also needs one period, since the score is read for exactly that period.
     */
    public static function isFor(bool $isSubtable, bool $severalPeriods): bool
    {
        return !$isSubtable && !$severalPeriods;
    }

    /**
     * The line's HTML, '' without a score (no checkable campaign visits). Texts escaped.
     *
     * @param array<string, mixed>|null $score a row of FeinwerkCampaignScore.getScore
     * @param callable $translate like Piwik::translate(string $key, array $args = [])
     */
    public static function html(?array $score, string $url, callable $translate, callable $formatPercent, callable $formatNumber): string
    {
        if ($score === null || !isset($score['score'])) {
            return '';
        }
        $percent = (string) $formatPercent((int) round((float) $score['score']));
        $findings = (int) ($score['findings'] ?? 0);
        // worded as an answer (UI review 2026-11-25 D8); no link when there is nothing to show
        if ($findings === 0) {
            return '<div class="fwcs-campaigns-footer">'
                . htmlspecialchars((string) $translate('FeinwerkCampaignScore_CampaignsFooterNone', [$percent]), ENT_QUOTES) . '</div>';
        }
        $text = $findings === 1
            ? (string) $translate('FeinwerkCampaignScore_CampaignsFooterOne', [$percent])
            : (string) $translate('FeinwerkCampaignScore_CampaignsFooter', [$percent, (string) $formatNumber($findings)]);

        return '<div class="fwcs-campaigns-footer">' . htmlspecialchars($text, ENT_QUOTES)
            . ' <a href="' . htmlspecialchars($url, ENT_QUOTES) . '">'
            . htmlspecialchars((string) $translate('FeinwerkCampaignScore_CampaignsFooterLink'), ENT_QUOTES) . '</a></div>';
    }
}
