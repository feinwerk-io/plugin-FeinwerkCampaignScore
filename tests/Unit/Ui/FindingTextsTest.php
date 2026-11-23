<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Ui;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Finding;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\FindingTexts;

/**
 * @group FeinwerkCampaignScore
 */
class FindingTextsTest extends TestCase
{
    public function test_everyTypeAndFieldHasATranslationInBothLanguages(): void
    {
        $keys = array_merge(array_values(FindingTexts::TYPES), array_values(FindingTexts::FIELDS));
        $this->assertSame(Finding::TYPES, array_keys(array_intersect_key(array_flip(Finding::TYPES), FindingTexts::TYPES)));
        $this->assertSame(['name', 'keyword', 'source', 'medium', 'content', 'group', 'placement'], array_keys(FindingTexts::FIELDS));
        foreach (['en', 'de'] as $language) {
            $texts = json_decode((string) file_get_contents(__DIR__ . '/../../../lang/' . $language . '.json'), true)['FeinwerkCampaignScore'];
            foreach ($keys as $key) {
                $this->assertNotEmpty($texts[substr($key, strlen('FeinwerkCampaignScore_'))] ?? '', $language . ' ' . $key);
            }
        }
    }

    public function test_value_showsVariantsWithTheirVisitsAndAMissingValueAsNotSet(): void
    {
        $translate = static function (string $key): string {
            return '[' . $key . ']';
        };
        $number = static function ($n): string {
            return (string) $n;
        };

        $this->assertSame('A (3), a (1)', FindingTexts::value('variants', 'A, a', ['A' => 3, 'a' => 1], $translate, $number));
        $this->assertSame('Black Friday', FindingTexts::value('format', 'Black Friday', ['Black Friday' => 4], $translate, $number));
        $this->assertSame('[FeinwerkCampaignScore_EmptyValue]', FindingTexts::value('incomplete', '', [], $translate, $number));
    }

    public function test_contexts_nameCampaignSourceAndMediumAndMissingValues(): void
    {
        $translate = static function (string $key): string {
            return '[' . $key . ']';
        };

        $this->assertSame(
            'winter-sale (facebook, Facebook Ads), Summer & Sale ([FeinwerkCampaignScore_EmptyValue], cpc)',
            FindingTexts::contexts([
                ['name' => 'winter-sale', 'source' => 'facebook', 'medium' => 'Facebook Ads', 'visits' => 15],
                ['name' => 'Summer &amp; Sale', 'source' => null, 'medium' => 'cpc', 'visits' => 3],
            ], $translate)
        );
        $this->assertSame('', FindingTexts::contexts([], $translate));
    }
}
