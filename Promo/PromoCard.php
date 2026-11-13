<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Promo;

/**
 * The card in a dashboard widget (house blueprint, docs/promotions.md "Dashboard widgets: the card"; Peter, 2026-11-13:
 * "Für das Dashboard Widget bitte die Werbung von ad 2: card nehmen", "Als Blaupause für Dashboard widgets merken").
 * A small tinted card after the widget's own link and before the feedback row: the product's mark, its name, one
 * sentence, a link and a x that hides only the card for 30 days in that browser (javascripts/promocard.js; markup in
 * templates/_promoCard.twig, styles in stylesheets/promocard.css). Shown under the same conditions as the rest of the
 * kit (PromoLine::shownToCurrentUser(): switches on, admins and super users) and never in a Widgetize embed. Pure.
 */
final class PromoCard
{
    /** localStorage fwpromo:<ID>:dismissed, its own key: hiding the card hides no other notice */
    public const ID = 'campaigns-card';
    public const DAYS = 30;
    public const PRODUCT = 'Feinwerk Campaigns';
    /** mtm_content of the link, to tell the card from the other placements */
    public const CONTENT = 'dashboard-card';

    /**
     * Variables of templates/_promoCard.twig, null when no card is shown.
     *
     * @param callable(string): string $translate
     * @return array{id: string, days: int, name: string, text: string, link: string, url: string, dismiss: string}|null
     */
    public static function variables(bool $shown, bool $embedded, string $language, callable $translate): ?array
    {
        if (!$shown || $embedded) {
            return null;
        }

        return [
            'id' => self::ID,
            'days' => self::DAYS,
            'name' => self::PRODUCT,
            'text' => $translate('FeinwerkCampaignScore_PromoCardText'),
            'link' => $translate('FeinwerkCampaignScore_PromoCardLink'),
            'url' => self::url($language),
            'dismiss' => $translate('FeinwerkCampaignScore_PromoDismiss'),
        ];
    }

    /** The landing page of Feinwerk Campaigns in the user's language (en otherwise) with the campaign of the card. */
    public static function url(string $language): string
    {
        $urls = PromoLine::MESSAGES['campaigns']['urls'];
        $base = $urls[$language === 'de' ? 'de' : 'en'] ?? $urls['en'];

        return $base . (strpos($base, '?') !== false ? '&' : '?')
            . 'mtm_campaign=plugin-promo&mtm_source=' . PromoLine::PLUGIN . '&mtm_content=' . self::CONTENT;
    }
}
