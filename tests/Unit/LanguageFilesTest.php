<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * At least English, German, French, Spanish and Italian (Peter, 2026-11-19: "bitte alle Plugins mindestens in
 * französisch, spanisch, italienisch, deutsch, englisch ausliefern"): every language file holds the keys of en.json in
 * its order, with the same placeholders, without en or em dashes and without URLs.
 *
 * @group FeinwerkCampaignScore
 */
class LanguageFilesTest extends TestCase
{
    private const LANGUAGES = ['en', 'de', 'fr', 'es', 'it'];

    /** @return array<string, string> */
    private static function texts(string $language): array
    {
        $path = __DIR__ . '/../../lang/' . $language . '.json';
        self::assertFileExists($path, "lang/$language.json is missing");
        $data = json_decode((string) file_get_contents($path), true);
        self::assertIsArray($data, "lang/$language.json is no JSON object");
        self::assertSame(['FeinwerkCampaignScore'], array_keys($data), "lang/$language.json: one section, the plugin's");

        return $data['FeinwerkCampaignScore'];
    }

    /** @return string[] the placeholders of a text, sorted */
    private static function placeholders(string $text): array
    {
        preg_match_all('/%(?:\d+\$)?s/', $text, $m);
        $found = $m[0];
        sort($found);

        return $found;
    }

    public function test_everyLanguage_hasTheKeysOfEnglishInTheirOrder(): void
    {
        $english = array_keys(self::texts('en'));
        foreach (self::LANGUAGES as $language) {
            $this->assertSame($english, array_keys(self::texts($language)), $language);
        }
    }

    /**
     * Exactly the placeholders of en.json, no exception: a translation with an extra %s shows it literally wherever a
     * caller passes no value (kit addendum 2026-11-24, PremiumAskSuperUser in German).
     */
    public function test_everyText_keepsThePlaceholdersOfEnglish(): void
    {
        $english = self::texts('en');
        foreach (self::LANGUAGES as $language) {
            foreach (self::texts($language) as $key => $text) {
                $found = self::placeholders($text);
                $this->assertSame(self::placeholders($english[$key]), $found, "$language: $key");
                $this->assertNotSame('', trim($text), "$language: $key is empty");
            }
        }
    }

    public function test_noText_hasADashOrAUrl(): void
    {
        foreach (self::LANGUAGES as $language) {
            foreach (self::texts($language) as $key => $text) {
                $this->assertSame(0, preg_match('/[\x{2013}\x{2014}]/u', $text), "$language: $key has an en or em dash");
                $this->assertStringNotContainsString('http', $text, "$language: $key has a URL");
            }
        }
    }
}
