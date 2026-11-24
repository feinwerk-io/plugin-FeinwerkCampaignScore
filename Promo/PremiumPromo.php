<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Promo;

use Piwik\Piwik;
use Piwik\Plugin\Manager as PluginManager;
use Piwik\Version;

/**
 * After the guide beside the score card is hidden, the paid Feinwerk plugins this Matomo does not have take its place,
 * one at a time in the same design (Peter, 2026-11-13: "Wenn das Banner weggeklickt wurde, rotiert Werbung für die
 * anderen Premium Plugins in analogem Design").
 *
 * Who and from where (Peter, 2026-11-16, relayed: admins too, and without a store connection; house docs
 * promotions.md "Paid-plugin recommendations for admins too", Page Filter Report's kit e162517):
 * - the connector's live list first: Promotions::paidPluginRecommendations() (admins and super users; super users get
 *   its install link, admins the public store page), else its older Promotions::paidPluginsNotInstalled() (super users);
 * - otherwise the plugin's own fallback list (PaidPluginCatalog): the plugins the store sells, minus the ones present;
 * - nothing when promotions are switched off, here (`promotions = 0`) or in the connector (PromoLine::enabled()).
 * Not inside Matomo for WordPress (Peter, 2026-11-17: "Dort nicht werben"): paid plugins cannot be installed there
 * yet (store ZIPs are no WordPress plugins), so the tile and the card stay empty, in demo too.
 * Demo mode (PromoLine::demo(), test instances): the connector's list topped up from the whole fallback list,
 * installed plugins and build floors ignored, every entry in the rotation, × keeps one away for the session.
 * Store links open in a new tab with the house campaign parameters; admins read one line that their super user can
 * install it. Same ids as Page Filter Report's tile (premium-<slug>), whatever the source, so hiding a plugin there or
 * here hides it in both.
 */
final class PremiumPromo
{
    /** how many candidates the rotation carries */
    public const MAX = 3;
    public const CONNECTOR = 'FeinwerkMarketplaceConnector';
    public const HELPER = 'Piwik\\Plugins\\FeinwerkMarketplaceConnector\\Promotions';

    /** campaign parameters of every store link (house docs promotions.md); mtm_content is rec-<slug> */
    public const CAMPAIGN = 'mtm_campaign=plugin-promo&mtm_source=FeinwerkCampaignScore&mtm_content=rec-';

    /** @var array<int, array<string, string>>|null the candidates of this request */
    private static $current = null;

    /**
     * The candidates for the current user in today's order, [] when there are none or promotions are off.
     *
     * @return array<int, array<string, string>>
     */
    public static function plugins(): array
    {
        if (self::$current === null) {
            try {
                // shownToCurrentUser(): every off switch, the connector's setting included, and the audience
                $demo = PromoLine::shownToCurrentUser() && PromoLine::demo();
                self::$current = PromoLine::shownToCurrentUser()
                    ? self::choose(self::valid(self::fromConnector(self::MAX)), static function () use ($demo): array {
                        return self::fromCatalog($demo);
                    }, intdiv(time(), 86400), $demo, PromoLine::isWordPress())
                    : [];
            } catch (\Throwable $e) {
                self::$current = [];
            }
        }

        return self::$current;
    }

    /**
     * The line for anyone who cannot install plugins, under a store link: who can (Matomo's own word for the super user).
     * '' for super users.
     */
    public static function askSuperUser(): string
    {
        try {
            return Piwik::hasUserSuperUserAccess()
                ? ''
                // a whole sentence in every language, no placeholder (kit addendum 2026-11-24)
                : Piwik::translate('FeinwerkCampaignScore_PremiumAskSuperUser');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * The connector's live list when it has one, else the fallback list; turned by one each day, at most MAX. In demo
     * mode the connector's list topped up with every fallback entry it lacks, all of them in the rotation.
     *
     * @param array<int, array<string, string>> $fromConnector valid entries of the connector
     * @param callable(): array<int, mixed>     $fallback      the fallback list (asked when the connector has none, or in demo)
     * @return array<int, array<string, string>>
     */
    public static function choose(array $fromConnector, callable $fallback, int $day, bool $demo = false, bool $wordpress = false): array
    {
        if ($wordpress) {
            return [];
        }
        if ($demo) {
            $slugs = array_column($fromConnector, 'slug');
            foreach (self::valid($fallback()) as $entry) {
                if (!in_array($entry['slug'], $slugs, true)) {
                    $fromConnector[] = $entry;
                }
            }

            return self::rotate($fromConnector, $day);
        }
        $list = $fromConnector !== [] ? $fromConnector : self::valid($fallback());

        return array_slice(self::rotate($list, $day), 0, self::MAX);
    }

    /**
     * The fallback list for this Matomo: the store's paid plugins (PaidPluginCatalog) with a build for this Matomo whose
     * folder is not here, in the viewer's language. One scan of the plugins directory. In demo mode the whole list.
     *
     * @return array<int, array<string, string>>
     */
    public static function fromCatalog(bool $demo = false): array
    {
        try {
            if ($demo) {
                return PaidPluginCatalog::all(PromoLine::language(), [Piwik::class, 'translate'], PromoLine::PLUGIN);
            }

            return PaidPluginCatalog::notInstalled(
                (array) PluginManager::getInstance()->readPluginsDirectory(),
                Version::VERSION,
                PromoLine::language(),
                [Piwik::class, 'translate'],
                PromoLine::PLUGIN
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** for tests: forget the candidates of this request */
    public static function reset(): void
    {
        self::$current = null;
    }

    /**
     * The connector's shared helper, guarded: paidPluginRecommendations() when the connector has it (admins and super
     * users), else paidPluginsNotInstalled() (super users). [] when the connector is not active, too old for both
     * helpers, not connected, or a helper fails. Never the connector's internals.
     *
     * @param callable|null $isActive test seam: whether the connector is activated (default: Matomo's plugin manager)
     * @param string        $helper   test seam: the helper class
     * @return array<int, mixed>
     */
    public static function fromConnector(int $max, ?callable $isActive = null, string $helper = self::HELPER): array
    {
        try {
            $isActive = $isActive ?? static function (): bool {
                return PluginManager::getInstance()->isPluginActivated(self::CONNECTOR);
            };
            if (!$isActive() || !class_exists($helper)) {
                return [];
            }
            foreach (['paidPluginRecommendations', 'paidPluginsNotInstalled'] as $method) {
                if (method_exists($helper, $method)) {
                    return (array) call_user_func([$helper, $method], $max);
                }
            }

            return [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Only entries this plugin can show: a Feinwerk slug, a name and a link, either inside Matomo (the connector's
     * plugin window: `installUrl`, or `url` of the older helper) or the plugin's public store page (`storeUrl`, exactly
     * PaidPluginCatalog::STORE{de|en}/plugins/<slug>), which gets the campaign parameters and opens in a new tab
     * (external = '1'). A picture only through the connector's image proxy inside Matomo. Id for the 30-day dismissal:
     * premium-<slug>, whatever the source.
     *
     * @param array<int, mixed> $list
     * @return array<int, array<string, string>>
     */
    public static function valid(array $list): array
    {
        $out = [];
        foreach ($list as $plugin) {
            if (!is_array($plugin)) {
                continue;
            }
            $slug = is_string($plugin['slug'] ?? null) ? $plugin['slug'] : '';
            $name = is_string($plugin['name'] ?? null) ? trim($plugin['name']) : '';
            $image = is_string($plugin['image'] ?? null) ? $plugin['image'] : '';
            if (!preg_match('/^Feinwerk[A-Za-z0-9]+$/', $slug) || $slug === PromoLine::PLUGIN || $name === ''
                || ($image !== '' && strpos($image, 'index.php?') !== 0)
            ) {
                continue;
            }
            [$url, $external] = self::link($plugin, $slug);
            if ($url === '') {
                continue;
            }
            $out[] = [
                'id' => 'premium-' . $slug,
                'slug' => $slug,
                'name' => $name,
                'description' => is_string($plugin['description'] ?? null) ? trim($plugin['description']) : '',
                'price' => is_string($plugin['price'] ?? null) ? trim($plugin['price']) : '',
                'image' => $image,
                'url' => $url,
                'external' => $external,
            ];
        }

        return $out;
    }

    /**
     * The link of an entry: the connector's plugin window inside Matomo when the entry has one, else the public store
     * page with the campaign parameters. ['', ''] when neither is usable.
     *
     * @param array<string, mixed> $plugin
     * @return array{0: string, 1: string} url, external ('1' for the store page)
     */
    private static function link(array $plugin, string $slug): array
    {
        foreach (['installUrl', 'url'] as $key) {
            $url = is_string($plugin[$key] ?? null) ? $plugin[$key] : '';
            if (strpos($url, 'index.php?') === 0) {
                return [$url, ''];
            }
        }
        $store = is_string($plugin['storeUrl'] ?? null) ? $plugin['storeUrl'] : '';
        foreach (['de', 'en'] as $language) {
            if ($store === PaidPluginCatalog::storeUrl($slug, $language)) {
                return [$store . '?' . self::CAMPAIGN . rawurlencode($slug), '1'];
            }
        }

        return ['', ''];
    }

    /**
     * Today's order: the list turned by one entry each day, so another plugin comes first.
     *
     * @param array<int, array<string, string>> $list
     * @return array<int, array<string, string>>
     */
    public static function rotate(array $list, int $day): array
    {
        $list = array_values($list);
        if ($list === []) {
            return [];
        }
        $start = $day % count($list);

        return array_merge(array_slice($list, $start), array_slice($list, 0, $start));
    }
}
