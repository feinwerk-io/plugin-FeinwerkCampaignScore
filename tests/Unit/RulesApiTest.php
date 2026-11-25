<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\API;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Convention;
use Piwik\Plugins\FeinwerkCampaignScore\Check\MediumList;
use Piwik\Plugins\FeinwerkCampaignScore\Data\ConventionFactory;
use Piwik\Plugins\FeinwerkCampaignScore\MeasurableSettings;

/**
 * The rules over the API (docs/ai-mcp.md rules 1 and 6): getRules names each rule and where it comes from;
 * removeAcceptedValues takes accepted values back; both write methods offer a dry run and report what changed.
 *
 * @group FeinwerkCampaignScore
 */
class RulesApiTest extends TestCase
{
    public function test_rules_say_where_each_comes_from(): void
    {
        $rules = ConventionFactory::rulesFrom(
            ['mediums' => "email\ncpc", 'separator' => '-', 'namePattern' => '', 'sourceAliases' => '1', 'typoHints' => '0'],
            ['mediums' => '', 'accepted' => "e-mail\nfb", 'separator' => '_', 'namePattern' => '^[a-z]+$']
        );

        $this->assertSame(['value' => 'underscore', 'source' => 'website'], $rules['separator']);
        $this->assertSame(['value' => '^[a-z]+$', 'source' => 'website'], $rules['name_pattern']);
        $this->assertSame(['value' => ['email', 'cpc'], 'source' => 'all_websites'], $rules['mediums']);
        $this->assertSame(['e-mail', 'fb'], $rules['accepted_values']);
        $this->assertTrue($rules['source_aliases']);
        $this->assertFalse($rules['typo_hints']);
    }

    public function test_a_website_that_allows_any_separator_overrides_all_websites(): void
    {
        $rules = ConventionFactory::rulesFrom(['separator' => '-'], ['separator' => Convention::SEPARATOR_ANY]);

        $this->assertSame(['value' => 'any', 'source' => 'website'], $rules['separator']);
    }

    public function test_settings_for_all_websites_at_their_default_count_as_default(): void
    {
        // Matomo's SystemSettings return the default when nothing is stored
        $rules = ConventionFactory::rulesFrom(
            ['mediums' => implode("\n", MediumList::DEFAULTS), 'separator' => Convention::SEPARATOR_ANY, 'namePattern' => ''],
            ['separator' => 'inherit']
        );

        $this->assertSame('default', $rules['separator']['source']);
        $this->assertSame('default', $rules['mediums']['source']);
        $this->assertSame(MediumList::DEFAULTS, $rules['mediums']['value']);
    }

    public function test_without_settings_the_defaults(): void
    {
        $rules = ConventionFactory::rulesFrom([], ['separator' => 'inherit']);

        $this->assertSame(['value' => 'any', 'source' => 'default'], $rules['separator']);
        $this->assertSame(['value' => null, 'source' => 'default'], $rules['name_pattern']);
        $this->assertSame(['value' => MediumList::DEFAULTS, 'source' => 'default'], $rules['mediums']);
        $this->assertSame([], $rules['accepted_values']);
    }

    public function test_accepting_reports_new_and_known_values(): void
    {
        $this->assertSame(
            ['added' => ['Partner Mail'], 'already' => ['fb']],
            MeasurableSettings::splitByPresence("e-mail\nfb", ['fb', ' Partner Mail ', '', 'fb'])
        );
    }

    public function test_taking_values_back(): void
    {
        $this->assertSame(["e-mail", ['fb'], ['unknown']], MeasurableSettings::withoutAccepted("e-mail\nfb", ['fb', 'unknown', '', 'fb']));
        $this->assertSame(['', [], []], MeasurableSettings::withoutAccepted('', []));
    }

    /** Admin access and POST are checked before anything is stored, and an empty list is a bad request. */
    public function test_remove_checks_access_and_post_first_and_refuses_no_value(): void
    {
        $method = new \ReflectionMethod(API::class, 'removeAcceptedValues');
        $lines = file((string) $method->getFileName());
        $body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        $admin = strpos($body, 'Piwik::checkUserHasAdminAccess($idSite)');
        $post = strpos($body, 'PluginFeedback::isAllowedMethod(');
        $empty = strpos($body, "FeinwerkCampaignScore_ErrorNoValue");
        $store = strpos($body, '->removeAccepted(');
        foreach ([$admin, $post, $empty] as $guard) {
            $this->assertNotFalse($guard);
            $this->assertLessThan($store, $guard);
        }
    }

    public function test_get_rules_needs_view_access(): void
    {
        $method = new \ReflectionMethod(API::class, 'getRules');
        $lines = file((string) $method->getFileName());
        $body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        $this->assertLessThan(strpos($body, 'rulesForSite('), strpos($body, 'Piwik::checkUserHasViewAccess($idSite)'));
    }

    /** @return string the source of one API method */
    private static function body(string $name): string
    {
        $method = new \ReflectionMethod(API::class, $name);
        $lines = file((string) $method->getFileName());

        return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }

    public function test_idSite_is_checked_first_and_dryRun_before_anything_is_stored(): void
    {
        foreach (['acceptValues' => '->accept(', 'removeAcceptedValues' => '->removeAccepted(', 'getRules' => 'rulesForSite('] as $name => $work) {
            $body = self::body($name);
            $site = strpos($body, '$this->oneSite($idSite)');
            $access = strpos($body, 'Piwik::checkUserHas');
            $this->assertNotFalse($site, $name);
            $this->assertLessThan($access, $site, $name . ': idSite before the access check');
            $this->assertLessThan(strpos($body, $work), $site, $name);
            if ($name !== 'getRules') {
                // a dryRun the API cannot read is refused, never taken as 0 (which saves)
                $this->assertLessThan(strpos($body, $work), strpos($body, 'self::dryRun($dryRun)'), $name);
                $this->assertStringNotContainsString('(bool) (int) $dryRun', $body, $name);
            }
        }
    }

    public function test_the_campaigns_footer_skips_embedded_widgets_before_reading_the_score(): void
    {
        $method = new \ReflectionMethod(\Piwik\Plugins\FeinwerkCampaignScore\FeinwerkCampaignScore::class, 'addCampaignsFooter');
        $lines = file((string) $method->getFileName());
        $body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        $embedded = strpos($body, 'FeedbackBar::isEmbedded(');
        $this->assertNotFalse($embedded);
        $this->assertLessThan(strpos($body, "processRequest('FeinwerkCampaignScore.getScore'"), $embedded);
    }
}
