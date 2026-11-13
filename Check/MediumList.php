<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Check;

/**
 * The mediums that count as usual. A clean medium must be in this list exactly.
 */
final class MediumList
{
    /**
     * Every clean medium value that Feinwerk Channel Grouping's default grouping (DefaultGroupingV2) assigns to a
     * channel, so both plugins agree on a clean medium (Peter, 2026-11-12).
     */
    public const DEFAULTS = [
        'affiliate', 'app', 'audio', 'banner', 'cpa', 'cpc', 'cpm', 'cpv', 'display', 'e-mail', 'e_mail', 'email',
        'expandable', 'interstitial', 'link', 'mobile', 'newsletter', 'notification', 'organic', 'paid', 'ppc',
        'push', 'referral', 'retargeting', 'sm', 'sms', 'social', 'social-media', 'social-network', 'video',
    ];

    /** @var array<string, true> */
    private $values;

    /**
     * @param list<string> $values
     */
    private function __construct(array $values)
    {
        $this->values = array_fill_keys($values, true);
    }

    /**
     * One medium per line or separated by commas; surrounding spaces and case do not matter. Empty: the defaults.
     */
    public static function fromText(string $text): self
    {
        $values = [];
        foreach (preg_split('/[\r\n,]+/', $text) ?: [] as $value) {
            $value = mb_strtolower(trim($value), 'UTF-8');
            if ($value !== '') {
                $values[$value] = true;
            }
        }

        return new self($values === [] ? self::DEFAULTS : array_keys($values));
    }

    public static function defaults(): self
    {
        return new self(self::DEFAULTS);
    }

    /**
     * @return list<string>
     */
    public function values(): array
    {
        return array_map('strval', array_keys($this->values));
    }

    public function contains(string $medium): bool
    {
        return isset($this->values[$medium]);
    }
}
