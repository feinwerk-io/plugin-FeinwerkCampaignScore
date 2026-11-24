<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Http\ApiErrors;

/**
 * API errors name the parameter, the value given and the allowed values (docs/ai-mcp.md rule 3), and never echo markup.
 */
class ApiErrorsTest extends TestCase
{
    /** @param array<int, string> $args */
    public static function translate(string $key, array $args = []): string
    {
        $texts = [
            'FeinwerkCampaignScore_ApiErrorPeriodNotHere' => 'Parameter period: "%1$s" is not possible here. Allowed: %2$s.',
            'FeinwerkCampaignScore_ApiErrorOnePeriod' => 'Parameter date: "%1$s" names several periods.',
            'FeinwerkCampaignScore_ApiErrorOnePeriodNoRange' => 'Parameter date: "%1$s" names several periods, no range.',
        ];

        return vsprintf($texts[$key] ?? $key, $args);
    }

    public function test_the_messages_name_parameter_value_and_allowed_values(): void
    {
        $this->assertSame(
            'Parameter period: "range" is not possible here. Allowed: day, week, month, year.',
            ApiErrors::periodNotHere('range', ApiErrors::SINGLE_PERIODS, [self::class, 'translate'])
        );
        $this->assertSame('Parameter date: "last8" names several periods.', ApiErrors::onePeriod('last8', [self::class, 'translate']));
        $this->assertSame(
            'Parameter date: "2026-11-01,2026-11-05" names several periods.',
            ApiErrors::onePeriod('2026-11-01,2026-11-05', [self::class, 'translate'])
        );
    }

    /** Regression (release review 2026-11-24): getScoresForAllSites must not advise period=range, which it refuses. */
    public function test_without_range_the_message_does_not_advise_a_range(): void
    {
        $this->assertSame(
            'Parameter date: "last7" names several periods, no range.',
            ApiErrors::onePeriod('last7', [self::class, 'translate'], false)
        );
        foreach (['en', 'de', 'fr', 'es', 'it'] as $language) {
            $texts = json_decode((string) file_get_contents(__DIR__ . '/../../lang/' . $language . '.json'), true)['FeinwerkCampaignScore'];
            $this->assertStringNotContainsString('range', $texts['ApiErrorOnePeriodNoRange'], $language);
        }
    }

    public function test_the_value_given_never_comes_back_as_markup(): void
    {
        $this->assertSame('scriptalert1script', ApiErrors::shown('<script>alert(1)</script>'));
        $this->assertSame('', ApiErrors::shown(['an', 'array']));
        $this->assertSame(40, strlen(ApiErrors::shown(str_repeat('a', 100))));
        $this->assertSame('dayonmouseover', ApiErrors::shown(' day" onmouseover='), 'quotes, spaces and = are dropped');
    }

    public function test_every_language_names_the_parameter_and_keeps_the_machine_values(): void
    {
        foreach (['en', 'de', 'fr', 'es', 'it'] as $language) {
            $texts = json_decode((string) file_get_contents(__DIR__ . '/../../lang/' . $language . '.json'), true)['FeinwerkCampaignScore'];
            $this->assertStringContainsString('period', $texts['ApiErrorPeriodNotHere'], $language);
            $this->assertStringContainsString('date', $texts['ApiErrorOnePeriod'], $language);
            $this->assertStringContainsString('period=range', $texts['ApiErrorOnePeriod'], $language);
            $this->assertStringContainsString('values', $texts['ErrorNoValue'], $language);
            foreach (['topic', 'vote', 'report', 'widget', 'up', 'down'] as $word) {
                $this->assertStringContainsString($word, $texts['ErrorFeedback'], $language . ' ' . $word);
            }
        }
    }
}
