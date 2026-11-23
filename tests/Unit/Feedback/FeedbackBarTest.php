<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Feedback;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Feedback\FeedbackBar;
use Piwik\Plugins\FeinwerkCampaignScore\Feedback\PluginFeedback;

/**
 * The question with thumbs and the support link: which questions, where, and the template variables. PHP 7.4 syntax.
 *
 * @group Plugins
 * @group FeinwerkCampaignScore
 */
class FeedbackBarTest extends TestCase
{
    public function test_every_question_has_a_text_and_is_accepted_by_the_api(): void
    {
        self::assertSame(PluginFeedback::TOPICS, array_keys(FeedbackBar::QUESTIONS));
        self::assertSame('report', FeedbackBar::TOPIC_REPORT);
        self::assertSame('widget', FeedbackBar::TOPIC_WIDGET);
    }

    public function test_only_under_the_report_itself_and_only_for_logged_in_users(): void
    {
        self::assertTrue(FeedbackBar::showsUnderReport(false, false));
        self::assertFalse(FeedbackBar::showsUnderReport(true, false), 'not under the pages of an expanded row');
        self::assertFalse(FeedbackBar::showsUnderReport(false, true), 'anonymous users cannot vote');
    }

    /** A report embedded with Widgetize is shown to the customer's own viewers: no Feinwerk question or link there. */
    public function test_not_in_embedded_widgets(): void
    {
        self::assertFalse(FeedbackBar::showsUnderReport(false, false, true));
        self::assertTrue(FeedbackBar::isEmbedded('Widgetize', 0));
        self::assertTrue(FeedbackBar::isEmbedded('FeinwerkCampaignScore', 1), 'disableLink=1');
        self::assertFalse(FeedbackBar::isEmbedded('CoreHome', 0), 'Matomo itself and its dashboards');
        self::assertFalse(FeedbackBar::isEmbedded('Dashboard', 0));
    }

    public function test_variables_of_the_template(): void
    {
        $translate = static function (string $key): string {
            return '[' . $key . ']';
        };
        self::assertSame([
            'topic' => 'report',
            'question' => '[FeinwerkCampaignScore_FeedbackQuestionReport]',
            'idSite' => 3,
            'enabled' => true,
            'supportUrl' => PluginFeedback::supportUrl('de'),
            'version' => '6.0.0-beta.1',
        ], FeedbackBar::variables('report', 3, true, 'de', '6.0.0-beta.1', $translate));
    }

    public function test_switched_off_shows_only_the_support_link(): void
    {
        $variables = FeedbackBar::variables('widget', 1, false, 'en', '6.0.0', static function (string $key): string {
            return $key;
        });
        self::assertFalse($variables['enabled']);
        self::assertSame(PluginFeedback::supportUrl('en'), $variables['supportUrl']);
    }

    public function test_unknown_topic_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FeedbackBar::variables('pricing', 1, true, 'en', '6.0.0', static function (string $key): string {
            return $key;
        });
    }

    /** The texts of the bar exist in both languages, without en or em dashes (house rule). */
    public function test_texts_in_german_and_english(): void
    {
        $keys = array_merge(array_values(FeedbackBar::QUESTIONS), array_map(static function (string $key): string {
            return 'FeinwerkCampaignScore_' . $key;
        }, ['FeedbackYes', 'FeedbackNo', 'FeedbackThanks', 'FeedbackThanksDown', 'Support', 'SupportTitle', 'ErrorFeedback']));
        foreach (['en', 'de'] as $language) {
            $texts = json_decode((string) file_get_contents(__DIR__ . '/../../../lang/' . $language . '.json'), true);
            foreach ($keys as $key) {
                $text = $texts['FeinwerkCampaignScore'][substr($key, strlen('FeinwerkCampaignScore_'))] ?? '';
                self::assertNotSame('', $text, $language . ' ' . $key);
                self::assertDoesNotMatchRegularExpression('/[\x{2013}\x{2014}]/u', $text, $language . ' ' . $key);
            }
        }
    }
}
