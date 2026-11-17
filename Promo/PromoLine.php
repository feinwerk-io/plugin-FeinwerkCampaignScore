<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Promo;

use Piwik\Common;
use Piwik\Config;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugin\Manager as PluginManager;
use Piwik\Plugin\ViewDataTable;
use Piwik\Site;
use Piwik\Translation\Translator;
use Piwik\View;

/**
 * The Feinwerk promotion kit (docs/promo-kit.md, house rule docs/promotions.md): the messages, their conditions and
 * off switches, and the tip line under the plugin's own report. The free placements (admin home banner, context boxes
 * on Matomo's reports) live in PromoPlacements and use the same messages. Everything is shown only to users with admin
 * access to at least one website and to super users, in Matomo's HTML interface only (not in Widgetize embeds, API
 * output, exports or email reports), never for a product that is already installed, and not at all when one of the off
 * switches says so (enabledBy()). Nothing is loaded from outside.
 *
 * Runs on PHP 7.4 and Matomo 5.0 to 6.x (the widest floor of the Feinwerk plugins).
 */
final class PromoLine
{
    public const PLUGIN = 'FeinwerkCampaignScore';
    /** days a dismissed message stays hidden in that browser (localStorage fwpromo:<id>:dismissed, javascripts/promo.js) */
    public const DISMISS_DAYS = 30;
    /** the Marketplace Connector: its config section and its UI setting switch promotions off for all Feinwerk plugins */
    public const CONNECTOR = 'FeinwerkMarketplaceConnector';
    private const CONNECTOR_SWITCH = '\\Piwik\\Plugins\\FeinwerkMarketplaceConnector\\Promotions';

    /**
     * message id => what it says and where it leads. product: technical name of an advertised plugin (shown only while
     * it is not installed), null for a website. urls: landing page per language, en is the fallback. placement: where a
     * free plugin shows it besides the tip line (PromoPlacements::PLACEMENTS). ecommerce: only for websites with
     * ecommerce. lineText, lineLink: another sentence for the tip line only. Copy approved by Peter on 2026-11-10.
     */
    public const MESSAGES = [
        'campaigns' => [
            'title' => 'FeinwerkCampaignScore_PromoCampaignsTitle',
            'text' => 'FeinwerkCampaignScore_PromoCampaignsText',
            'link' => 'FeinwerkCampaignScore_PromoCampaignsLink',
            // Campaign Score's own sentence for the line under its report (CampaignsWeb, approved by Peter 2026-11-12)
            'lineText' => 'FeinwerkCampaignScore_PromoCampaignsLineText',
            'lineLink' => 'FeinwerkCampaignScore_PromoCampaignsLineLink',
            'urls' => ['de' => 'https://campaigns.feinwerk.io/de/matomo', 'en' => 'https://campaigns.feinwerk.io/en/matomo'],
            'product' => null,
            'placement' => 'campaigns',
            'ecommerce' => false,
        ],
        'hosting' => [
            'title' => 'FeinwerkCampaignScore_PromoHostingTitle',
            'text' => 'FeinwerkCampaignScore_PromoHostingText',
            'link' => 'FeinwerkCampaignScore_PromoHostingLink',
            'urls' => [
                'de' => 'https://feinwerk.io/de/kontakt?service=hosting',
                'en' => 'https://feinwerk.io/en/contact?service=hosting',
            ],
            'product' => null,
            'placement' => 'adminHome',
            'ecommerce' => false,
        ],
        'capi' => [
            'title' => 'FeinwerkCampaignScore_PromoCapiTitle',
            'text' => 'FeinwerkCampaignScore_PromoCapiText',
            'link' => 'FeinwerkCampaignScore_PromoCapiLink',
            'urls' => [
                'de' => 'https://plugins.feinwerk.io/de/plugins/FeinwerkConversionsApi',
                'en' => 'https://plugins.feinwerk.io/en/plugins/FeinwerkConversionsApi',
            ],
            'product' => 'FeinwerkConversionsApi',
            'placement' => 'ecommerce',
            'ecommerce' => true,
        ],
        'sentinel' => [
            'title' => 'FeinwerkCampaignScore_PromoSentinelTitle',
            'text' => 'FeinwerkCampaignScore_PromoSentinelText',
            'link' => 'FeinwerkCampaignScore_PromoSentinelLink',
            'urls' => [
                'de' => 'https://plugins.feinwerk.io/de/plugins/FeinwerkSentinel',
                'en' => 'https://plugins.feinwerk.io/en/plugins/FeinwerkSentinel',
            ],
            'product' => 'FeinwerkSentinel',
            'placement' => 'goals',
            'ecommerce' => false,
        ],
    ];

    /**
     * Paid plugins prefer this message for their tip line, free plugins rotate (null; docs/promotions.md). Campaign
     * Score is free, but it leads to Feinwerk Campaigns: its line always shows campaigns (Peter, 2026-11-12).
     */
    public const PREFERRED = 'campaigns';

    /**
     * Demo mode for Feinwerk's test instances (Peter, 2026-11-16: "kannst du einen demo modus einführen, damit stats6
     * auch wieder die werbung zeigt?", docs/promotions.md "Demo mode for test instances"): `promotions_demo = 1` in
     * [FeinwerkMarketplaceConnector] of config.ini.php, also without the connector. Every off switch still wins and the
     * audience stays admins and super users; demo only lifts the conditions (installed products, build floors) and
     * keeps a hidden notice away for the browser session instead of 30 days.
     */
    public const DEMO = 'promotions_demo';

    /** @var bool|null demo mode of this request */
    private static $demo = null;

    /**
     * The off switches, in this order (docs/promo-kit.md, "Off switches"): promotions = 0 in the plugin's own config
     * section, promotions = 0 in [FeinwerkMarketplaceConnector] (all Feinwerk plugins, read even without the
     * connector), then the connector's setting in Matomo's UI. A failing connector switch counts as on.
     *
     * @param array<string, mixed> $section the plugin's config section
     * @param array<string, mixed> $connectorSection [FeinwerkMarketplaceConnector] of config.ini.php
     * @param callable|null $connectorSwitch the connector's Promotions::areEnabled, null when there is none
     */
    public static function enabledBy(array $section, array $connectorSection = [], ?callable $connectorSwitch = null): bool
    {
        if (!self::switchOn($section) || !self::switchOn($connectorSection)) {
            return false;
        }
        if ($connectorSwitch === null) {
            return true;
        }
        try {
            return (bool) $connectorSwitch();
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * The demo switch, read like the connector's Promotions::demo(): 1, on, yes and true (any case, Matomo's INI reader
     * may also hand over PHP true or 1) are on, everything else is off.
     *
     * @param array<string, mixed> $connectorSection [FeinwerkMarketplaceConnector] of config.ini.php
     */
    public static function demoBy(array $connectorSection): bool
    {
        $value = $connectorSection[self::DEMO] ?? null;
        if ($value === true || $value === 1) {
            return true;
        }
        if (!is_string($value) && !is_int($value)) {
            return false;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'on', 'yes', 'true'], true);
    }

    /** Demo mode for this request, read once. Only asked behind enabled(): an off switch beats it. */
    public static function demo(): bool
    {
        if (self::$demo === null) {
            try {
                $connector = Config::getInstance()->{self::CONNECTOR};
                self::$demo = self::demoBy(is_array($connector) ? $connector : []);
            } catch (\Throwable $e) {
                self::$demo = false;
            }
        }

        return self::$demo;
    }

    /**
     * How long a hidden notice stays away: 30 days, in demo 0 (the kit's scripts then keep it away for the browser
     * session only, and older copies of them on the page only).
     */
    public static function dismissDays(bool $demo): int
    {
        return $demo ? 0 : self::DISMISS_DAYS;
    }

    public static function enabled(): bool
    {
        $config = Config::getInstance();
        $own = $config->{self::PLUGIN};
        $connector = $config->{self::CONNECTOR};

        return self::enabledBy(is_array($own) ? $own : [], is_array($connector) ? $connector : [], self::connectorSwitch());
    }

    /**
     * The connector's setting "Show notes on other Feinwerk offers", when the connector is activated and has it (from
     * its release with Promotions::areEnabled); null otherwise.
     *
     * @param callable(): bool|null $isActivated whether the connector is activated (null: ask Matomo)
     */
    public static function connectorSwitch(string $class = self::CONNECTOR_SWITCH, ?callable $isActivated = null): ?callable
    {
        try {
            $isActivated = $isActivated ?? static function (): bool {
                return PluginManager::getInstance()->isPluginActivated(self::CONNECTOR);
            };
            if (!class_exists($class) || !method_exists($class, 'areEnabled') || !$isActivated()) {
                return null;
            }
            return static function () use ($class): bool {
                return (bool) $class::areEnabled();
            };
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * One config section's promotions switch. Matomo's INI reader turns off, no and false into PHP false; an empty
     * value counts as off as well, like the feedback switch.
     *
     * @param array<string, mixed> $section
     */
    private static function switchOn(array $section): bool
    {
        if (!array_key_exists('promotions', $section)) {
            return true;
        }
        $value = $section['promotions'];

        return $value !== false && $value !== null
            && !in_array(strtolower(trim((string) $value)), ['0', '', 'off', 'no', 'false'], true);
    }

    /** Only admins of at least one website and super users, never anonymous users. */
    public static function visibleTo(bool $isAnonymous, bool $hasSomeAdminAccess): bool
    {
        return !$isAnonymous && $hasSomeAdminAccess;
    }

    /** A report embedded with Widgetize (module=Widgetize or disableLink=1) is seen by the customer's own viewers. */
    public static function isEmbedded(string $module, int $disableLink): bool
    {
        return $module === 'Widgetize' || $disableLink === 1;
    }

    /**
     * Messages whose condition holds: the advertised product is not installed, and a message for ecommerce only on a
     * website with ecommerce.
     *
     * @param callable(string): bool $isInstalled
     * @return string[] message ids in the order of MESSAGES
     */
    public static function candidates(callable $isInstalled, bool $isEcommerceSite = false, bool $demo = false): array
    {
        if ($demo) {
            // demo: the whole rotation, whatever is installed or tracked
            return array_keys(self::MESSAGES);
        }
        $ids = [];
        foreach (self::MESSAGES as $id => $message) {
            if (!empty($message['ecommerce']) && !$isEcommerceSite) {
                continue;
            }
            if ($message['product'] === null || !$isInstalled($message['product'])) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * The preferred message when there is one and it is a candidate, otherwise one per day in turn; null when none is
     * left.
     *
     * @param string[] $candidates
     */
    public static function choose(array $candidates, int $day): ?string
    {
        if ($candidates === []) {
            return null;
        }
        if (self::PREFERRED !== null && in_array(self::PREFERRED, $candidates, true)) {
            return self::PREFERRED;
        }

        return array_values($candidates)[$day % count($candidates)];
    }

    /** Landing page in the user's language (German or English) with the campaign parameters for Feinwerk's Matomo. */
    public static function url(string $id, string $language): string
    {
        $urls = self::MESSAGES[$id]['urls'];
        $base = $urls[$language === 'de' ? 'de' : 'en'] ?? $urls['en'];

        return $base . (strpos($base, '?') !== false ? '&' : '?')
            . 'mtm_campaign=plugin-promo&mtm_source=' . self::PLUGIN . '&mtm_content=' . rawurlencode($id);
    }

    /**
     * Variables of templates/_promo.twig and templates/_promoBox.twig. Pure.
     *
     * @param callable(string): string $translate
     * @return array{id: string, placement: string, title: string, text: string, link: string, url: string, dismissDays: int, demo: bool}
     */
    public static function variables(string $id, string $language, callable $translate, string $placement = 'line', bool $demo = false): array
    {
        if (!isset(self::MESSAGES[$id])) {
            throw new \InvalidArgumentException('message');
        }
        $message = self::MESSAGES[$id];
        $line = $placement === 'line';

        return [
            'id' => $id,
            'placement' => $placement,
            'title' => $translate($message['title']),
            'text' => $translate($line && isset($message['lineText']) ? $message['lineText'] : $message['text']),
            'link' => $translate($line && isset($message['lineLink']) ? $message['lineLink'] : $message['link']),
            'url' => self::url($id, $language),
            'dismissDays' => self::dismissDays($demo),
            'demo' => $demo,
        ];
    }

    /** Whether the current user may see promotions at all: switches on, admin of a website or super user. */
    public static function shownToCurrentUser(): bool
    {
        return self::enabled() && self::visibleTo(Piwik::isUserIsAnonymous(), Piwik::isUserHasSomeAdminAccess());
    }

    /** Whether a plugin is installed (the advertised product of a message). */
    public static function isInstalled(string $plugin): bool
    {
        return PluginManager::getInstance()->isPluginInstalled($plugin);
    }

    public static function language(): string
    {
        return (string) StaticContainer::get(Translator::class)->getCurrentLanguage();
    }

    /** The line for the current user and website, null when nothing is shown. */
    public static function current(int $idSite): ?array
    {
        if (!self::shownToCurrentUser()) {
            return null;
        }
        $isEcommerceSite = $idSite > 0 && Site::isEcommerceEnabledFor($idSite);
        $demo = self::demo();
        $id = self::choose(self::candidates([self::class, 'isInstalled'], $isEcommerceSite, $demo), intdiv(time(), 86400));

        return $id === null ? null : self::variables($id, self::language(), [Piwik::class, 'translate'], 'line', $demo);
    }

    /** The line as HTML for the footer of a report view; '' where it is not shown and on any error (never breaks a report). */
    public static function forReport(ViewDataTable $view): string
    {
        try {
            if ($view->requestConfig->idSubtable || self::isEmbedded(Piwik::getModule(), Common::getRequestVar('disableLink', 0, 'int'))) {
                return '';
            }
            $promo = self::current(Common::getRequestVar('idSite', 0, 'int'));

            return $promo === null ? '' : self::render($promo);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** @param array<string, mixed> $promo see variables() */
    public static function render(array $promo): string
    {
        $view = new View('@FeinwerkCampaignScore/_promo');
        $view->promo = $promo;

        return $view->render();
    }
}
