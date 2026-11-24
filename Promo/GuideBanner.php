<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Promo;

use Piwik\Plugins\FeinwerkCampaignScore\Data\ConventionExport;

/**
 * Campaign Score's primary placement (Peter, 2026-11-13; plugin CLAUDE.md "Primary ad placements"): beside the score card
 * on the report page, a banner that is a short guide to Feinwerk Campaigns: why (the missing points), then export the
 * convention, upload it, build clean links only. The exports meant for Feinwerk Campaigns live in this banner. Its x
 * hides it for 30 days; then the paid Feinwerk plugins follow in the same place (PremiumPromo, javascripts/promocard.js).
 * Only where promotions show (PromoLine::shownToCurrentUser()), never in a Widgetize embed. Pure.
 */
final class GuideBanner
{
    /** localStorage fwpromo:<ID>:dismissed; differs from every message id of promo.js */
    public const ID = 'campaigns-guide';
    /** one x empties the whole banner place for these days (Peter, 2026-11-24: "Ein × blendet alles 30 Tage aus") */
    public const DAYS = 30;
    /**
     * each banner of the place (guide and paid plugins) stays away this long after its own x, longer than the pause, so
     * after the pause the next banner of the rotation shows instead of the same one (javascripts/scoresplit.js)
     */
    public const ITEM_DAYS = 90;
    /** mtm_content of its links */
    public const CONTENT = 'report-guide';

    /**
     * Variables of the guide in templates/_scoreSplit.twig, null when it is not shown.
     *
     * @param float|null $score the score of the period, null without campaign visits
     * @param string|null $exportUrl the download of the convention, null when there is nothing to export
     * @param callable(string, array): string $translate Piwik::translate
     * @param bool $demo demo mode (PromoLine::demo()): × keeps it away for the browser session only
     * @return array{id: string, days: int, pauseDays: int, label: string, title: string, text: string, guideTitle: string,
     *     steps: list<array{title: string, text: string, url: string, download: bool}>, cta: string, url: string,
     *     dismiss: string}|null
     */
    public static function variables(bool $shown, bool $embedded, ?float $score, ?string $exportUrl, string $language,
                                     callable $translate, bool $demo = false): ?array
    {
        if (!$shown || $embedded) {
            return null;
        }
        $gap = $score !== null && $score < 100 ? max(1, (int) round(100 - $score)) : 0;
        $url = self::url($language);
        // the steps follow the file of step 1: without a convention to export (no campaign visits in the period) the
        // guide shows its text and link only (UI review 2026-11-23: "Upload it" named a file nobody was offered)
        $steps = [];
        if ($exportUrl !== null && $exportUrl !== '') {
            $steps[] = ['title' => $translate('FeinwerkCampaignScore_GuideStepExport', []),
                'text' => $translate('FeinwerkCampaignScore_GuideStepExportText', []), 'url' => $exportUrl, 'download' => true];
            $steps[] = ['title' => $translate('FeinwerkCampaignScore_GuideStepUpload', []),
                'text' => $translate('FeinwerkCampaignScore_GuideStepUploadText', []), 'url' => ConventionExport::IMPORT_URL,
                'download' => false];
            $steps[] = ['title' => $translate('FeinwerkCampaignScore_GuideStepBuild', []),
                'text' => $translate('FeinwerkCampaignScore_GuideStepBuildText', []), 'url' => $url, 'download' => false];
        }

        return [
            'id' => self::ID,
            'days' => $demo ? 0 : self::ITEM_DAYS,
            'pauseDays' => $demo ? 0 : self::DAYS,
            'demo' => $demo,
            'label' => $translate('FeinwerkCampaignScore_GuideLabel', []),
            'title' => PromoCard::PRODUCT,
            'text' => $gap > 0
                ? $translate('FeinwerkCampaignScore_GuideTextGap', [$gap])
                : $translate('FeinwerkCampaignScore_GuideText', []),
            'guideTitle' => $translate('FeinwerkCampaignScore_GuideTitle', []),
            'steps' => $steps,
            'cta' => $translate('FeinwerkCampaignScore_GuideCta', []),
            'url' => $url,
            'dismiss' => $translate('FeinwerkCampaignScore_GuideDismiss', []),
        ];
    }

    /** The landing page of Feinwerk Campaigns in the user's language (en otherwise) with the campaign of the guide. */
    public static function url(string $language): string
    {
        $urls = PromoLine::MESSAGES['campaigns']['urls'];
        $base = $urls[$language === 'de' ? 'de' : 'en'] ?? $urls['en'];

        return $base . (strpos($base, '?') !== false ? '&' : '?')
            . 'mtm_campaign=plugin-promo&mtm_source=' . PromoLine::PLUGIN . '&mtm_content=' . self::CONTENT;
    }
}
