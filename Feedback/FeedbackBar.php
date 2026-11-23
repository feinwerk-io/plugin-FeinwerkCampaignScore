<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Feedback;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugin\Manager as PluginManager;
use Piwik\Plugin\ViewDataTable;
use Piwik\Translation\Translator;
use Piwik\View;

/**
 * The question with thumbs up and down and the support link (templates/_feedback.twig, behaviour in
 * javascripts/feedback.js), one per view: under the findings of the Campaign Score report and in the dashboard widget
 * (there with a support link no bigger than the thumbs). Switched off (config or Matomo's internet features), only the
 * support link remains.
 */
final class FeedbackBar
{
    public const TOPIC_REPORT = 'report';
    public const TOPIC_WIDGET = 'widget';

    /** question (store "topic") => its text; the same topics as PluginFeedback::TOPICS */
    public const QUESTIONS = [
        self::TOPIC_REPORT => 'FeinwerkCampaignScore_FeedbackQuestionReport',
        self::TOPIC_WIDGET => 'FeinwerkCampaignScore_FeedbackQuestionWidget',
    ];

    /**
     * Whether a report view gets the bar: the report itself, not the pages of an expanded row, only for a logged-in
     * user (the API method refuses anonymous votes) and not in a Widgetize embed. Pure.
     */
    public static function showsUnderReport(bool $isSubtable, bool $isAnonymous, bool $isEmbedded = false): bool
    {
        return !$isSubtable && !$isAnonymous && !$isEmbedded;
    }

    /**
     * A report embedded in another page through Widgetize (module=Widgetize, disableLink=1) is seen by the customer's
     * own viewers: no Feinwerk question and support link there. Matomo's dashboards are not embeds.
     */
    public static function isEmbedded(string $module, int $disableLink): bool
    {
        return $module === 'Widgetize' || $disableLink === 1;
    }

    /**
     * Variables of templates/_feedback.twig. Pure.
     *
     * @param callable(string): string $translate
     * @return array{topic: string, question: string, idSite: int, enabled: bool, supportUrl: string, version: string}
     */
    public static function variables(string $topic, int $idSite, bool $enabled, string $language, string $version, callable $translate): array
    {
        if (!isset(self::QUESTIONS[$topic])) {
            throw new \InvalidArgumentException('topic');
        }

        return [
            'topic' => $topic,
            'question' => $translate(self::QUESTIONS[$topic]),
            'idSite' => $idSite,
            'enabled' => $enabled,
            'supportUrl' => PluginFeedback::supportUrl($language),
            'version' => $version,
        ];
    }

    /** Variables for the current request: Matomo's switches, the user's language, the installed plugin version. */
    public static function current(string $topic, int $idSite): array
    {
        return self::variables(
            $topic,
            $idSite,
            PluginFeedback::enabled(),
            (string) StaticContainer::get(Translator::class)->getCurrentLanguage(),
            (string) PluginManager::getInstance()->getLoadedPlugin(PluginFeedback::PLUGIN)->getVersion(),
            [Piwik::class, 'translate']
        );
    }

    /** The bar as HTML for the footer of a report view; '' where it is not shown and on any error (never breaks a report). */
    public static function forReport(string $topic, ViewDataTable $view, int $idSite): string
    {
        try {
            $embedded = self::isEmbedded(Piwik::getModule(), Common::getRequestVar('disableLink', 0, 'int'));
            if (!self::showsUnderReport((bool) $view->requestConfig->idSubtable, Piwik::isUserIsAnonymous(), $embedded)) {
                return '';
            }

            return self::render(self::current($topic, $idSite));
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** The bar for the plugin's dashboard widget; '' for anonymous users and on any error. */
    public static function forPage(string $topic, int $idSite): string
    {
        try {
            return Piwik::isUserIsAnonymous() ? '' : self::render(self::current($topic, $idSite));
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** @param array<string, mixed> $feedback see variables() */
    public static function render(array $feedback): string
    {
        $view = new View('@FeinwerkCampaignScore/_feedback');
        $view->feedback = $feedback;

        return $view->render();
    }
}
