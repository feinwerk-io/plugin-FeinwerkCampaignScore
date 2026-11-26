<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Check;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Analyzer;
use Piwik\Plugins\FeinwerkCampaignScore\Check\CampaignData;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Convention;
use Piwik\Plugins\FeinwerkCampaignScore\Check\FieldChecker;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Finding;
use Piwik\Plugins\FeinwerkCampaignScore\Check\MediumList;
use Piwik\Plugins\FeinwerkCampaignScore\Check\ValueRules;
use Piwik\Plugins\FeinwerkCampaignScore\Data\ConventionFactory;

/**
 * Campaign names of digits only are findings and lower the score (Peter, 2026-11-26: "auch scoring vergeben, wenn
 * kampagnen namen nur numerisch sind"); a setting for all websites switches it off.
 *
 * @group FeinwerkCampaignScore
 */
class NumericNamesTest extends TestCase
{
    public function test_digits_only_means_no_letter_in_any_script(): void
    {
        foreach (['12345', '2026', '2026-11', '123_456', '12.34', ' 99 '] as $value) {
            $this->assertTrue(ValueRules::isNumericOnly($value), $value);
        }
        foreach (['summer-2026', '2026-sale', 'q4', 'ä1', '', '-_.', 'черный-1'] as $value) {
            $this->assertFalse(ValueRules::isNumericOnly($value), $value);
        }
    }

    public function test_a_numeric_campaign_name_is_a_finding_without_suggestion(): void
    {
        $findings = FieldChecker::check('name', ['12345' => 40, 'summer-sale' => 60, '2026 11' => 5]);

        $types = [];
        foreach ($findings as $finding) {
            $types[(string) array_key_first($finding->values)] = [$finding->type, $finding->suggestion];
        }
        $this->assertSame([Finding::NUMERIC, null], $types['12345']);
        // digits only comes before the format: "2026-11" would not be a readable name either
        $this->assertSame([Finding::NUMERIC, null], $types['2026 11']);
        $this->assertArrayNotHasKey('summer-sale', $types);
    }

    public function test_only_the_campaign_name_is_checked_for_numbers(): void
    {
        foreach (['source', 'medium', 'keyword', 'content', 'group', 'placement'] as $field) {
            $types = array_map(static function (Finding $finding): string {
                return $finding->type;
            }, FieldChecker::check($field, ['12345' => 10], new Convention(MediumList::fromText('12345'))));
            $this->assertNotContains(Finding::NUMERIC, $types, $field);
        }
    }

    public function test_numeric_names_lower_the_score_and_cost_points(): void
    {
        $data = new CampaignData(
            100,
            false,
            ['name' => ['summer-sale' => 60, '987654321' => 40], 'keyword' => []],
            [],
            [['name' => 'summer-sale', 'source' => null, 'medium' => null, 'visits' => 60], ['name' => '987654321', 'source' => null, 'medium' => null, 'visits' => 40]],
            0
        );

        $result = Analyzer::analyze($data, Convention::defaults());
        $this->assertEqualsWithDelta(0.6, $result->score, 0.0001);
        $this->assertSame(Finding::NUMERIC, $result->findings[0]->type);
        $this->assertSame(40.0, $result->findings[0]->scoreGain);

        $off = ConventionFactory::build(['numericNames' => '0'], []);
        $this->assertFalse($off->numericNames);
        $this->assertSame(1.0, Analyzer::analyze($data, $off)->score, 'switched off: digits are clean again');

        $accepted = new Convention(MediumList::defaults(), ['987654321' => true]);
        $this->assertSame(1.0, Analyzer::analyze($data, $accepted)->score, 'a value marked as correct is clean');
    }

    public function test_get_rules_reports_the_setting(): void
    {
        $this->assertTrue(ConventionFactory::rulesFrom([], [])['numeric_names']);
        $this->assertFalse(ConventionFactory::rulesFrom(['numericNames' => false], [])['numeric_names']);
    }
}
