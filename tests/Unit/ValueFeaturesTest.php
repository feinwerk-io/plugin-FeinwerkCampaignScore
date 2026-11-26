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
use Piwik\Plugins\FeinwerkCampaignScore\Check\Finding;
use Piwik\Plugins\FeinwerkCampaignScore\Check\MediumList;
use Piwik\Plugins\FeinwerkCampaignScore\Data\ConventionFactory;
use Piwik\Plugins\FeinwerkCampaignScore\Http\InvalidInputException;
use Piwik\Plugins\FeinwerkCampaignScore\MeasurableSettings;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\EmailReport;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\FindingTexts;

/**
 * The value release (Peter, 2026-11-25: "Alle offenen punkte umsetzen"): points per fix, the filter by type, hints instead
 * of "-", spelling variants as chips, additional usual mediums and the email report.
 *
 * @group FeinwerkCampaignScore
 */
class ValueFeaturesTest extends TestCase
{
    /** @param array<int, string> $args */
    public static function translate(string $key, array $args = []): string
    {
        return '[' . $key . ($args === [] ? '' : ':' . implode('|', $args)) . ']';
    }

    private static function number(): callable
    {
        return static function ($number): string {
            return (string) $number;
        };
    }

    private static function body(string $class, string $method): string
    {
        $reflection = new \ReflectionMethod($class, $method);
        $lines = file((string) $reflection->getFileName());

        return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
    }

    public function test_every_finding_without_a_suggestion_says_what_to_do(): void
    {
        $translate = [self::class, 'translate'];
        $this->assertSame('[FeinwerkCampaignScore_HintIncomplete]', FindingTexts::hint(Finding::INCOMPLETE, 'medium', $translate));
        $this->assertSame('[FeinwerkCampaignScore_HintUnusualMedium]', FindingTexts::hint(Finding::UNUSUAL_MEDIUM, 'medium', $translate));
        $this->assertSame('[FeinwerkCampaignScore_HintPattern]', FindingTexts::hint(Finding::PATTERN, 'name', $translate));
        $this->assertSame('[FeinwerkCampaignScore_HintSimilar]', FindingTexts::hint(Finding::SIMILAR, 'name', $translate));
        $this->assertSame('[FeinwerkCampaignScore_HintChangeInLinks]', FindingTexts::hint(Finding::FORMAT, 'name', $translate));
    }

    public function test_spelling_variants_become_escaped_chips_with_the_clean_one_marked(): void
    {
        $chips = FindingTexts::chips(['Black Friday' => 7, 'black-friday' => 40, '&lt;b&gt;x' => 1], 'black-friday', self::number());

        $this->assertStringStartsWith('<span class="fwcs-chips" aria-hidden="true">', $chips);
        $this->assertStringContainsString('<span class="fwcs-chip">Black Friday<span class="fwcs-chip__count">7</span></span>', $chips);
        $this->assertStringContainsString('<span class="fwcs-chip fwcs-chip--clean"><span class="fwcs-chip__mark">&#10003;</span>black-friday', $chips);
        // Matomo stores values HTML-encoded: decoded, then escaped once
        $this->assertStringContainsString('&lt;b&gt;x', $chips);
        $this->assertStringNotContainsString('&amp;lt;', $chips);
        $this->assertSame('', FindingTexts::chips([], '', self::number()));
    }

    public function test_the_filter_lists_the_types_present_with_their_counts(): void
    {
        $html = FindingTexts::filter([Finding::UNUSUAL_MEDIUM => 2, Finding::VARIANTS => 6, Finding::PATTERN => 0], [], [self::class, 'translate'], self::number());

        $this->assertStringContainsString('data-fwcs-type="" aria-pressed="true">[FeinwerkCampaignScore_FilterAll] <span class="fwcs-filter__count">8</span>', $html);
        // in the order of Finding::TYPES: variants before unusual medium; no chip for a type without findings
        $this->assertLessThan(strpos($html, 'data-fwcs-type="unusual_medium"'), strpos($html, 'data-fwcs-type="variants"'));
        $this->assertStringNotContainsString('data-fwcs-type="pattern"', $html);
        $this->assertStringContainsString('aria-label="[FeinwerkCampaignScore_FilterLabel]"', $html);

        $pressed = FindingTexts::filter([Finding::UNUSUAL_MEDIUM => 2, Finding::VARIANTS => 6], [Finding::VARIANTS], [self::class, 'translate'], self::number());
        $this->assertStringContainsString('is-active" data-fwcs-type="variants" aria-pressed="true"', $pressed);
        $this->assertStringContainsString('data-fwcs-type="" aria-pressed="false"', $pressed);

        $this->assertSame('', FindingTexts::filter([Finding::VARIANTS => 6], [], [self::class, 'translate'], self::number()), 'one type: nothing to filter');
    }

    public function test_finding_type_is_checked_and_bad_values_name_the_parameter(): void
    {
        $types = new \ReflectionMethod(API::class, 'findingTypes');
        $types->setAccessible(true);

        $this->assertSame([], $types->invoke(null, ''));
        $this->assertSame(['variants', 'unusual_medium'], $types->invoke(null, ' variants,unusual_medium,variants '));
        try {
            $types->invoke(null, 'variants,spam', [self::class, 'translate']);
            $this->fail('an unknown type must be refused');
        } catch (InvalidInputException $e) {
            $this->assertStringContainsString('[FeinwerkCampaignScore_ApiErrorBadValue:findingType|spam|incomplete, variants', $e->getMessage());
        }
    }

    public function test_additional_mediums_add_to_the_list_in_force(): void
    {
        $this->assertSame(['email', 'paid-social'], MediumList::parse(" Email\npaid-social, email \n"));
        $this->assertSame([], MediumList::parse(''));

        $convention = ConventionFactory::build(['mediums' => "email\ncpc"], ['extraMediums' => "Paid-Social\nemail"]);
        $this->assertSame(['email', 'cpc', 'paid-social'], $convention->mediums->values());
        $this->assertTrue($convention->mediums->contains('paid-social'));

        $rules = ConventionFactory::rulesFrom(['mediums' => "email\ncpc"], ['extraMediums' => 'paid-social']);
        $this->assertSame(['email', 'cpc', 'paid-social'], $rules['mediums']['value']);
        $this->assertSame(['paid-social'], $rules['additional_mediums']);
    }

    public function test_adding_and_removing_mediums_reports_what_changed(): void
    {
        [$text, $added, $already] = MeasurableSettings::withMediums("podcast", ['email', 'cpc', 'podcast'], ['Paid-Social', 'email', 'paid-social', 'podcast']);
        $this->assertSame("podcast\npaid-social", $text);
        $this->assertSame(['paid-social'], $added);
        $this->assertSame(['email', 'podcast'], $already);

        [$text, $removed, $notFound] = MeasurableSettings::withoutMediums("podcast\npaid-social", ['PODCAST', 'email']);
        $this->assertSame('paid-social', $text);
        $this->assertSame(['podcast'], $removed);
        $this->assertSame(['email'], $notFound);
    }

    public function test_medium_methods_check_site_access_and_post_before_saving(): void
    {
        foreach (['addUsualMediums' => '->addMediums(', 'removeUsualMediums' => '->removeMediums('] as $method => $store) {
            $body = self::body(API::class, $method);
            $guards = [
                strpos($body, '$this->oneSite($idSite)'),
                strpos($body, 'Piwik::checkUserHasAdminAccess($idSite)'),
                strpos($body, 'PluginFeedback::isAllowedMethod('),
                strpos($body, 'FeinwerkCampaignScore_ErrorNoValue'),
                strpos($body, 'self::dryRun($dryRun)'),
            ];
            foreach ($guards as $guard) {
                $this->assertNotFalse($guard, $method);
                $this->assertLessThan(strpos($body, $store), $guard, $method);
            }
        }
    }

    public function test_the_email_report_is_made_once_per_user_and_website(): void
    {
        $reports = [
            ['idreport' => 3, 'login' => 'anna', 'reports' => ['FeinwerkCampaignScore_getScore']],
            ['idreport' => 4, 'login' => 'ben', 'reports' => '["VisitsSummary_get"]'],
            ['idreport' => 5, 'login' => 'ben', 'reports' => '["FeinwerkCampaignScore_getScore","FeinwerkCampaignScore_getFindings"]'],
        ];
        $this->assertSame(5, EmailReport::existing($reports, 'ben')['idreport']);
        $this->assertSame(3, EmailReport::existing($reports, 'anna')['idreport']);
        $this->assertNull(EmailReport::existing($reports, 'carla'), 'another user\'s report is not hers');

        $parameters = EmailReport::addReportParameters(7, 'week', 'Campaign Score');
        $this->assertSame(['FeinwerkCampaignScore_getScore', 'FeinwerkCampaignScore_getFindings'], $parameters['reports']);
        $this->assertSame(['email', 'html', 'week', 7], [$parameters['reportType'], $parameters['reportFormat'], $parameters['period'], $parameters['idSite']]);
        $this->assertTrue($parameters['parameters']['emailMe']);

        $body = self::body(API::class, 'addEmailReport');
        $create = strpos($body, "'ScheduledReports.addReport'");
        foreach (['$this->oneSite($idSite)', 'Piwik::checkUserIsNotAnonymous()', 'Piwik::checkUserHasViewAccess($idSite)', 'PluginFeedback::isAllowedMethod(', 'EmailReport::PERIODS', 'EmailReport::existing('] as $guard) {
            $this->assertLessThan($create, strpos($body, $guard), $guard);
        }
    }

    public function test_the_trend_per_website_needs_a_score_in_both_periods(): void
    {
        $rows = \Piwik\Plugins\FeinwerkCampaignScore\Data\AllSites::withPrevious(
            [1 => ['score' => 88.0, 'findings' => 3], 2 => ['findings' => 0], 3 => ['score' => 70.0]],
            [1 => ['score' => 80.1], 2 => ['score' => 90.0], 3 => ['findings' => 0]]
        );
        $this->assertSame(['score' => 88.0, 'findings' => 3, 'previous_score' => 80.1, 'score_change' => 7.9], $rows[1]);
        $this->assertSame(['findings' => 0, 'previous_score' => 90.0], $rows[2], 'no score now: no change');
        $this->assertSame(['score' => 70.0], $rows[3], 'no score before: nothing to compare');

        $body = self::body(API::class, 'getScoresForAllSites');
        $this->assertLessThan(strpos($body, '$this->scoreRows('), strpos($body, 'ApiErrors::flag($withPreviousPeriod)'), 'a bad compare is refused before any read');
        $this->assertStringContainsString('md5(serialize([$conventions, $compare]))', $body, 'compared and plain tables are cached apart');
    }

    /** Code review 2026-11-26: "tv,radio" are two mediums, not "tv-radio"; remove compares the clean spelling too. */
    public function test_mediums_split_at_commas_and_lines(): void
    {
        $split = new \ReflectionMethod(API::class, 'mediumList');
        $split->setAccessible(true);
        $this->assertSame(['tv', 'radio', 'Facebook Ads'], $split->invoke(null, "tv,radio\nFacebook Ads"));
        $this->assertSame(['tv', 'radio'], $split->invoke(null, ['tv, radio', ' ']));

        $clean = new \ReflectionMethod(API::class, 'cleanMediums');
        $clean->setAccessible(true);
        $this->assertSame(['tv', 'facebook-ads'], $clean->invoke(null, ['tv', 'Facebook Ads'], 'any'));

        $remove = self::body(API::class, 'removeUsualMediums');
        $this->assertStringContainsString('self::cleanMediums($list', $remove);
        $this->assertStringContainsString('self::mediumList($values)', self::body(API::class, 'addUsualMediums'));
    }

    public function test_get_findings_checks_access_before_the_type(): void
    {
        $body = self::body(API::class, 'getFindings');
        $this->assertLessThan(strpos($body, 'self::findingTypes('), strpos($body, 'Piwik::checkUserHasViewAccess('));
    }
}
