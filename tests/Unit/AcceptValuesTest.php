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
use Piwik\Plugins\FeinwerkCampaignScore\Http\InvalidInputException;
use Piwik\Plugins\FeinwerkCampaignScore\MeasurableSettings;

/**
 * Accepting values: the guards of the API method (review 2026-11-13) and the limits of the stored list.
 *
 * @group FeinwerkCampaignScore
 */
class AcceptValuesTest extends TestCase
{
    /** Admin access and POST are checked before anything is stored. */
    public function test_theGuardsComeFirst(): void
    {
        $method = new \ReflectionMethod(API::class, 'acceptValues');
        $lines = file((string) $method->getFileName());
        $body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        $admin = strpos($body, 'Piwik::checkUserHasAdminAccess($idSite)');
        $post = strpos($body, 'PluginFeedback::isAllowedMethod(');
        $store = strpos($body, '->accept(');

        $this->assertNotFalse($admin);
        $this->assertNotFalse($post);
        $this->assertNotFalse($store);
        $this->assertLessThan($store, $admin);
        $this->assertLessThan($store, $post);
    }

    public function test_merge_addsNewValuesOnceAndKeepsTheOldOnes(): void
    {
        $this->assertSame("e-mail\nfb\nPartner Mail", MeasurableSettings::mergeAccepted("e-mail\nfb", ['fb', ' Partner Mail ', '']));
    }

    public function test_merge_refusesMoreThanTheLimit(): void
    {
        $existing = implode("\n", array_map(static function (int $i): string {
            return 'value-' . $i;
        }, range(1, MeasurableSettings::MAX_ACCEPTED)));

        $this->expectException(\LengthException::class);
        MeasurableSettings::mergeAccepted($existing, ['one-more']);
    }

    /**
     * Wrong input is the caller's error: HTTP 400 through the plugin's own InvalidInputException (Matomo sets the status
     * from an HttpCodeException), not a plain exception that ends as 500, and not
     * core's BadRequestException, which drops the code on Matomo 5.0.0 (the status would stay 200 there).
     */
    public function test_inputErrorsOfTheApiAreBadRequests(): void
    {
        $source = (string) file_get_contents((string) (new \ReflectionClass(API::class))->getFileName());

        $this->assertStringContainsString('use Piwik\\Plugins\\FeinwerkCampaignScore\\Http\\InvalidInputException;', $source);
        $this->assertStringNotContainsString('BadRequestException', $source);
        $this->assertSame(0, preg_match('/throw new \\\\(InvalidArgumentException|Exception)\(/', $source), 'no plain exception for input errors');
        $error = new InvalidInputException('x');
        $this->assertSame(400, $error->getCode());
        $this->assertInstanceOf(\Piwik\Http\HttpCodeException::class, $error);
    }
}
