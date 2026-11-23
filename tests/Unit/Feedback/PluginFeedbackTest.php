<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Feedback;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Feedback\PluginFeedback;

/**
 * The server side of the feedback kit: the store contract, the switches, the throttles, and that nothing but the
 * contract fields leaves the server. PHP 7.4 syntax (the plugin's 5.x line).
 *
 * @group Plugins
 * @group FeinwerkCampaignScore
 */
class PluginFeedbackTest extends TestCase
{
    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36';

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_VIA']);
    }

    public function test_payload_follows_the_store_contract(): void
    {
        self::assertSame([
            'plugin' => 'FeinwerkCampaignScore',
            'plugin_version' => '6.0.0-beta.1',
            'matomo_version' => '6.0.0-rc1',
            'topic' => 'report',
            'vote' => 'down',
            'browser' => 'Chrome 141',
            'language' => 'de',
        ], PluginFeedback::payload('report', 'down', '6.0.0-beta.1', '6.0.0-rc1', self::CHROME, 'de'));
    }

    public function test_topic_and_vote_are_trimmed(): void
    {
        // Matomo 6 no longer trims request parameters before they reach the API method
        $payload = PluginFeedback::payload(" widget\n", ' up ', '6.0.0', '6.0.0', '', 'en');
        self::assertSame('widget', $payload['topic']);
        self::assertSame('up', $payload['vote']);
    }

    public function test_every_question_of_the_plugin_is_accepted(): void
    {
        self::assertSame(['report', 'widget'], PluginFeedback::TOPICS);
        foreach (PluginFeedback::TOPICS as $topic) {
            self::assertMatchesRegularExpression('/^[a-z_]{1,30}$/', $topic, 'store pattern of topic');
            self::assertSame($topic, PluginFeedback::payload($topic, 'up', '1', '6', '', 'en')['topic']);
        }
    }

    public function test_only_the_questions_the_plugin_asks(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PluginFeedback::payload('pricing', 'up', '6.0.0', '6.0.0', '', 'en');
    }

    public function test_only_up_or_down(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PluginFeedback::payload('report', 'maybe', '6.0.0', '6.0.0', '', 'en');
    }

    public function test_unknown_browser_and_language_are_null(): void
    {
        $payload = PluginFeedback::payload('widget', 'up', '6.0.0', '6.0.0', 'curl/8.5.0', '');
        self::assertNull($payload['browser']);
        self::assertNull($payload['language']);
    }

    public function test_versions_and_language_are_cut_to_the_store_limits(): void
    {
        $payload = PluginFeedback::payload('widget', 'up', str_repeat('1', 40), str_repeat('6', 40), '', 'de"><script>alert(1)</script>');
        self::assertSame(30, mb_strlen($payload['plugin_version']));
        self::assertSame(30, mb_strlen($payload['matomo_version']));
        self::assertMatchesRegularExpression('/^[A-Za-z_-]{1,10}$/', $payload['language']);
    }

    public function test_plugin_name_matches_the_store_pattern(): void
    {
        self::assertMatchesRegularExpression('/^Feinwerk[A-Za-z0-9]{3,52}$/', PluginFeedback::payload('report', 'up', '1', '6', '', 'en')['plugin']);
    }

    public function test_browser_is_family_and_major_version_only(): void
    {
        self::assertSame('Chrome 141', PluginFeedback::browser(self::CHROME));
        self::assertSame('Edge 140', PluginFeedback::browser('Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.3485.54'));
        self::assertSame('Firefox 143', PluginFeedback::browser('Mozilla/5.0 (X11; Linux x86_64; rv:143.0) Gecko/20100101 Firefox/143.0'));
        self::assertSame('Safari 18', PluginFeedback::browser('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Safari/605.1.15'));
        self::assertSame('Opera 122', PluginFeedback::browser('Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 OPR/122.0.0.0'));
        self::assertSame('', PluginFeedback::browser('curl/8.5.0'));
    }

    public function test_support_opens_the_contact_form_with_the_plugins_topic(): void
    {
        $tracking = 'mtm_campaign=plugin-support&mtm_source=FeinwerkCampaignScore';
        self::assertSame('https://feinwerk.io/de/kontakt?service=plugins&' . $tracking, PluginFeedback::supportUrl('de'));
        self::assertSame('https://feinwerk.io/en/contact?service=plugins&' . $tracking, PluginFeedback::supportUrl('en'));
        self::assertSame('https://feinwerk.io/en/contact?service=plugins&' . $tracking, PluginFeedback::supportUrl('fr'));
    }

    /** Matomo's INI reader turns off, no and false into PHP false; an empty value counts as off as well (feedback kit). */
    public function test_every_spelling_of_off_switches_it_off(): void
    {
        self::assertTrue(PluginFeedback::enabledBy([]));
        foreach ([false, null, '', ' ', '0', 0, ' 0 ', 'off', 'Off', 'no', 'false', 'FALSE'] as $value) {
            self::assertFalse(PluginFeedback::enabledBy(['feedback' => $value]), var_export($value, true));
        }
        foreach ([true, 1, '1', 'on', 'yes'] as $value) {
            self::assertTrue(PluginFeedback::enabledBy(['feedback' => $value]), var_export($value, true));
        }
    }

    /** Test runs never send a vote, and neither does a Matomo with its internet features off. */
    public function test_nothing_is_sent_in_test_mode_or_without_internet_features(): void
    {
        self::assertFalse(PluginFeedback::enabledBy([], true, true));
        self::assertFalse(PluginFeedback::enabledBy(['feedback' => '1'], true, true));
        self::assertFalse(PluginFeedback::enabledBy([], false));
        self::assertTrue(PluginFeedback::enabledBy([], true, false));
    }

    /** The class refuses too: with Matomo's real HTTP client nothing leaves a test run, a test double may send. */
    public function test_the_real_client_never_sends_in_test_mode(): void
    {
        if (!defined('PIWIK_TEST_MODE')) {
            define('PIWIK_TEST_MODE', true);
        }
        self::assertFalse((new PluginFeedback())->isEnabled());
        self::assertFalse((new PluginFeedback())->send(['plugin' => 'FeinwerkCampaignScore']));
        self::assertTrue((new PluginFeedback(static function (...$args) {
            return ['status' => 202];
        }))->isEnabled());
    }

    public function test_the_server_sends_at_most_twenty_votes_a_minute(): void
    {
        $now = 1800000000; // 30000000 minutes exactly
        $stored = null;
        for ($i = 1; $i <= PluginFeedback::SERVER_CAP; $i++) {
            $stored = PluginFeedback::countVote($stored, $now);
            self::assertSame('30000000:' . $i, $stored);
        }
        self::assertNull(PluginFeedback::countVote($stored, $now + 59), 'the 21st vote of the minute');
        self::assertSame('30000001:1', PluginFeedback::countVote($stored, $now + 60), 'a new minute');
        self::assertSame('30000000:1', PluginFeedback::countVote('garbage', $now));
        self::assertSame('fw_cs_feedback.server', PluginFeedback::SERVER_OPTION);
        self::assertStringStartsNotWith(PluginFeedback::userOptionPrefix('server'), PluginFeedback::SERVER_OPTION);
    }

    public function test_each_question_has_its_own_throttle(): void
    {
        $report = PluginFeedback::throttleOption('anna', 'report');
        self::assertNotSame($report, PluginFeedback::throttleOption('anna', 'widget'), 'a second question still counts');
        self::assertNotSame($report, PluginFeedback::throttleOption('bert', 'report'));
        self::assertStringStartsWith(PluginFeedback::userOptionPrefix('anna'), $report, 'deleted with the user');
        self::assertStringNotContainsString('anna', $report, 'the login is not stored in plain text');
        self::assertStringStartsWith('fw_cs_', PluginFeedback::THROTTLE_OPTION_PREFIX, 'house prefix, deleted on uninstall');
    }

    public function test_the_store_of_the_connector_is_used_when_there_is_one(): void
    {
        self::assertSame('https://store.example/api/v1/feedback', PluginFeedback::endpoint(FakeConnectorFactory::class));
        self::assertSame('https://plugins.feinwerk.io/api/v1/feedback', PluginFeedback::endpoint('\\Piwik\\Plugins\\NoSuchPlugin\\MarketplaceClientFactory'));
        self::assertSame('https://plugins.feinwerk.io/api/v1/feedback', PluginFeedback::endpoint(BrokenConnectorFactory::class), 'a failing connector');
        self::assertSame('https://plugins.feinwerk.io/api/v1/feedback', PluginFeedback::endpoint(EmptyConnectorFactory::class));
    }

    public function test_sends_the_vote_as_json_by_post_with_a_short_timeout_and_no_browser_details(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = self::CHROME;
        $_SERVER['HTTP_VIA'] = '1.1 corporate-proxy.example';
        $seen = [];
        $transport = static function (...$args) use (&$seen) {
            $seen = $args;
            $seen['via'] = $_SERVER['HTTP_VIA'] ?? null;
            return ['status' => 202, 'data' => '{"ok":true}'];
        };
        $payload = PluginFeedback::payload('report', 'up', '6.0.0-beta.1', '6.0.0-rc1', self::CHROME, 'en');

        self::assertTrue((new PluginFeedback($transport))->send($payload));
        self::assertStringEndsWith('/api/v1/feedback', $seen[1]);
        self::assertSame(3, $seen[2], 'timeout in seconds, the same in every plugin');
        self::assertSame(PluginFeedback::USER_AGENT, $seen[3]);
        self::assertSame('POST', $seen[11]);
        self::assertSame($payload, json_decode($seen[14], true));
        self::assertContains('Content-Type: application/json', $seen[15]);
        self::assertNull($seen['via'], 'the incoming proxy chain must not go into the Via header');
        self::assertSame('1.1 corporate-proxy.example', $_SERVER['HTTP_VIA'], 'restored after the request');
    }

    public function test_a_rejected_vote_or_an_unreachable_store_is_silent(): void
    {
        foreach ([422, 429, 500, 0] as $status) {
            $transport = static function (...$args) use ($status) {
                return ['status' => $status, 'data' => ''];
            };
            self::assertFalse((new PluginFeedback($transport))->send(['plugin' => 'FeinwerkCampaignScore']), (string) $status);
        }
        $exception = static function (...$args) {
            throw new \Exception('timeout');
        };
        $error = static function (...$args) {
            throw new \TypeError('broken transport');
        };
        self::assertFalse((new PluginFeedback($exception))->send(['plugin' => 'FeinwerkCampaignScore']));
        self::assertFalse((new PluginFeedback($error))->send(['plugin' => 'FeinwerkCampaignScore']));
    }
}

/** Test doubles of the Marketplace Connector's MarketplaceClientFactory. */
class FakeConnectorFactory
{
    public static function url(): string
    {
        return 'https://store.example/';
    }
}

class BrokenConnectorFactory
{
    public static function url(): string
    {
        throw new \Error('settings missing');
    }
}

class EmptyConnectorFactory
{
    public static function url(): string
    {
        return '';
    }
}
