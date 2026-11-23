<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Promo;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\View;

/**
 * Under "Campaign data findings": one link with the mark of Feinwerk Campaigns in place of the tip line (Peter,
 * 2026-11-13: "Footer Link mit Feinwerk Logo ... in 'Campaign data findings' footer sehen"; one ad line stays). Only
 * where promotions show (PromoLine::shownToCurrentUser()), not in subtables, never in a Widgetize embed; the mark is
 * inline, nothing is loaded from outside.
 */
final class FooterLink
{
    /** mtm_content of the link */
    public const CONTENT = 'findings-footer';

    /**
     * Variables of templates/_footerLink.twig, null when it is not shown. Pure.
     *
     * @param callable(string, array): string $translate Piwik::translate
     * @return array{url: string, text: string}|null
     */
    public static function variables(bool $shown, bool $embedded, bool $subtable, string $language, callable $translate): ?array
    {
        if (!$shown || $embedded || $subtable) {
            return null;
        }

        return ['url' => self::url($language), 'text' => $translate('FeinwerkCampaignScore_FooterLinkText', [])];
    }

    /** The landing page of Feinwerk Campaigns in the user's language (en otherwise) with the campaign of the link. */
    public static function url(string $language): string
    {
        $urls = PromoLine::MESSAGES['campaigns']['urls'];
        $base = $urls[$language === 'de' ? 'de' : 'en'] ?? $urls['en'];

        return $base . (strpos($base, '?') !== false ? '&' : '?')
            . 'mtm_campaign=plugin-promo&mtm_source=' . PromoLine::PLUGIN . '&mtm_content=' . self::CONTENT;
    }

    /** The link as HTML for the footer of the findings; '' where it is not shown and on any error (never breaks a report). */
    public static function forReport(ViewDataTable $view): string
    {
        try {
            $link = self::variables(
                PromoLine::shownToCurrentUser(),
                PromoLine::isEmbedded(Piwik::getModule(), Common::getRequestVar('disableLink', 0, 'int')),
                (bool) $view->requestConfig->idSubtable,
                PromoLine::language(),
                [Piwik::class, 'translate']
            );
            if ($link === null) {
                return '';
            }
            $html = new View('@FeinwerkCampaignScore/_footerLink');
            $html->footLink = $link;

            return $html->render();
        } catch (\Throwable $e) {
            return '';
        }
    }
}
