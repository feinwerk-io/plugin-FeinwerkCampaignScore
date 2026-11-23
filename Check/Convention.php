<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Check;

/**
 * The naming convention a website's campaign values are checked against: the usual mediums, values the admins
 * accepted, the separator inside values, an optional pattern for campaign names, and whether source aliases and typo
 * hints are on. Built from the system and website settings (Settings/ConventionSettings).
 */
final class Convention
{
    public const SEPARATOR_ANY = 'any';
    public const SEPARATOR_HYPHEN = '-';
    public const SEPARATOR_UNDERSCORE = '_';

    /**
     * @param array<string, true> $accepted values that count as clean in every field
     * @param string|null $namePattern a PCRE pattern without delimiters for campaign names; an invalid one is ignored
     */
    public function __construct(
        public readonly MediumList $mediums,
        public readonly array $accepted = [],
        public readonly string $separator = self::SEPARATOR_ANY,
        public readonly ?string $namePattern = null,
        public readonly bool $sourceAliases = true,
        public readonly bool $typoHints = true
    ) {
    }

    public static function defaults(): self
    {
        return new self(MediumList::defaults());
    }

    /**
     * One value per line, surrounding spaces removed, case kept.
     *
     * @return array<string, true>
     */
    public static function acceptedFromText(string $text): array
    {
        $accepted = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $accepted[$line] = true;
            }
        }
        return $accepted;
    }

    public function isAccepted(string $value): bool
    {
        return isset($this->accepted[$value]);
    }

    public static function isValidPattern(string $pattern): bool
    {
        return $pattern === '' || @preg_match(self::delimit($pattern), '') !== false;
    }

    public function matchesNamePattern(string $value): bool
    {
        if ($this->namePattern === null || $this->namePattern === '' || !self::isValidPattern($this->namePattern)) {
            return true;
        }
        return preg_match(self::delimit($this->namePattern), $value) === 1;
    }

    /**
     * The value with the separator of the convention, or null when it already follows the rule. Without a rule only
     * values that mix "-" and "_" break it; they get the more frequent of the two (hyphen on a tie).
     */
    public function separatorFix(string $value): ?string
    {
        $hyphens = substr_count($value, '-');
        $underscores = substr_count($value, '_');
        if ($this->separator === self::SEPARATOR_HYPHEN) {
            return $underscores > 0 ? str_replace('_', '-', $value) : null;
        }
        if ($this->separator === self::SEPARATOR_UNDERSCORE) {
            return $hyphens > 0 ? str_replace('-', '_', $value) : null;
        }
        if ($hyphens === 0 || $underscores === 0) {
            return null;
        }
        return $underscores > $hyphens ? str_replace('-', '_', $value) : str_replace('_', '-', $value);
    }

    /** A control character as delimiter: it cannot be typed into the setting, so the pattern needs no escaping. */
    private static function delimit(string $pattern): string
    {
        return "\x01" . str_replace("\x01", '', $pattern) . "\x01u";
    }
}
