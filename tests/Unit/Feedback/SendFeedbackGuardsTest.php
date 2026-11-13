<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Feedback;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\API;
use Piwik\Plugins\FeinwerkCampaignScore\Feedback\PluginFeedback;

/**
 * The guards of the public API method sendFeedback. Calling the method needs Matomo's access layer and database, which
 * these unit tests do not have; so the POST rule is tested directly and the order of the guards is pinned in the
 * method's source, so a refactoring that drops one fails here. Unknown questions and votes are refused by
 * PluginFeedback::payload() (PluginFeedbackTest). PHP 7.4 syntax.
 *
 * @group Plugins
 * @group FeinwerkCampaignScore
 */
class SendFeedbackGuardsTest extends TestCase
{
    public function test_only_post_casts_a_vote(): void
    {
        self::assertTrue(PluginFeedback::isAllowedMethod('POST'));
        self::assertTrue(PluginFeedback::isAllowedMethod('post'));
        foreach (['GET', 'HEAD', 'PUT', '', ' '] as $method) {
            self::assertFalse(PluginFeedback::isAllowedMethod($method), var_export($method, true));
        }
    }

    /** Anonymous users, users without view access to the site and GET requests are refused before anything is built. */
    public function test_the_guards_come_first(): void
    {
        $method = new \ReflectionMethod(API::class, 'sendFeedback');
        $lines = file((string) $method->getFileName());
        $body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        $anonymous = strpos($body, 'Piwik::checkUserIsNotAnonymous()');
        $view = strpos($body, 'Piwik::checkUserHasViewAccess((int) $idSite)');
        $post = strpos($body, 'PluginFeedback::isAllowedMethod(');
        $payload = strpos($body, 'PluginFeedback::payload(');

        self::assertNotFalse($anonymous, 'anonymous users are refused');
        self::assertNotFalse($view, 'view access to the site');
        self::assertNotFalse($post, 'POST only');
        self::assertNotFalse($payload);
        self::assertLessThan($payload, $anonymous);
        self::assertLessThan($payload, $view);
        self::assertLessThan($payload, $post);
        self::assertStringContainsString("Piwik::translate('FeinwerkCampaignScore_ErrorFeedback')", $body, 'a refused question or vote gets the translated error');
    }
}
