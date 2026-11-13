<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Feedback;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Feedback\FeedbackVotes;
use Piwik\Plugins\FeinwerkCampaignScore\Feedback\PluginFeedback;

/**
 * One vote of the API method sendFeedback: the switch, the throttle per user and question, the cap per server, sent
 * once. PHP 7.4 syntax (the 5.x line).
 *
 * @group Plugins
 * @group FeinwerkCampaignScore
 */
class FeedbackVotesTest extends TestCase
{
    /** @var array<string, string> */
    private $options = [];
    /** @var int */
    private $now = 1800000000;
    /** @var string[] JSON bodies the fake store received */
    private $sent = [];
    /** @var int */
    private $status = 202;

    private function votes(): FeedbackVotes
    {
        $transport = function (...$args) {
            $this->sent[] = $args[14];
            return ['status' => $this->status, 'data' => ''];
        };

        return new FeedbackVotes(
            new PluginFeedback($transport),
            function (string $key) {
                return $this->options[$key] ?? false;
            },
            function (string $key, string $value): void {
                $this->options[$key] = $value;
            },
            function (): int {
                return $this->now;
            }
        );
    }

    private static function payload(string $topic = 'report', string $vote = 'up'): array
    {
        return PluginFeedback::payload($topic, $vote, '6.0.0-beta.1', '6.0.0-rc1', '', 'de');
    }

    public function test_a_vote_goes_to_the_store(): void
    {
        self::assertTrue($this->votes()->cast('anna', self::payload(), true));
        self::assertCount(1, $this->sent);
        self::assertSame(self::payload(), json_decode($this->sent[0], true));
        self::assertSame((string) $this->now, $this->options[PluginFeedback::throttleOption('anna', 'report')]);
    }

    public function test_nothing_is_sent_when_feedback_is_switched_off(): void
    {
        self::assertFalse($this->votes()->cast('anna', self::payload(), false));
        self::assertSame([], $this->sent);
        self::assertSame([], $this->options, 'no throttle written either');
    }

    public function test_one_vote_per_user_and_question_every_ten_seconds(): void
    {
        $votes = $this->votes();
        self::assertTrue($votes->cast('anna', self::payload('report', 'up'), true));
        $this->now += 9;
        self::assertFalse($votes->cast('anna', self::payload('report', 'down'), true), 'same question, 9 s later');
        self::assertTrue($votes->cast('anna', self::payload('widget', 'down'), true), 'another question');
        self::assertTrue($votes->cast('bert', self::payload('report', 'down'), true), 'another user');
        $this->now += 1;
        self::assertTrue($votes->cast('anna', self::payload('report', 'down'), true), '10 s later');
        self::assertCount(4, $this->sent);
    }

    /** All users and questions together: at most SERVER_CAP votes a minute leave this Matomo. */
    public function test_the_server_cap_counts_all_users_and_questions(): void
    {
        $votes = $this->votes();
        for ($i = 0; $i < PluginFeedback::SERVER_CAP; $i++) {
            self::assertTrue($votes->cast('user' . $i, self::payload(), true));
        }
        self::assertFalse($votes->cast('another', self::payload('widget'), true));
        self::assertCount(PluginFeedback::SERVER_CAP, $this->sent);
        $this->now += 60;
        self::assertTrue($votes->cast('another', self::payload('widget'), true), 'the next minute');
    }

    /** A vote stopped by the cap does not use up the user's ten seconds: both are stored only when sending. */
    public function test_a_capped_vote_leaves_the_users_throttle_alone(): void
    {
        $this->options[PluginFeedback::SERVER_OPTION] = intdiv($this->now, 60) . ':' . PluginFeedback::SERVER_CAP;

        self::assertFalse($this->votes()->cast('anna', self::payload(), true));
        self::assertArrayNotHasKey(PluginFeedback::throttleOption('anna', 'report'), $this->options);
    }

    public function test_a_store_that_rejects_the_vote_gives_no_error(): void
    {
        $this->status = 429;
        self::assertFalse($this->votes()->cast('anna', self::payload(), true));
        self::assertCount(1, $this->sent, 'sent once, no retry');
    }
}
