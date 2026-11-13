<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Check;

/**
 * Common other spellings of large platforms as campaign source: "fb" or "m.facebook.com" mean "facebook". Keyed by
 * the normalized form (ValueRules::normalize), so case, dots, hyphens and spaces do not matter. Deliberately short:
 * only spellings that cannot mean anything else.
 */
final class SourceAliases
{
    /** normalized spelling => canonical source */
    public const MAP = [
        'fb' => 'facebook',
        'facebookcom' => 'facebook',
        'mfacebookcom' => 'facebook',
        'lfacebookcom' => 'facebook',
        'lmfacebookcom' => 'facebook',
        'facebookads' => 'facebook',
        'ig' => 'instagram',
        'instagramcom' => 'instagram',
        'linstagramcom' => 'instagram',
        'adwords' => 'google',
        'googleads' => 'google',
        'googleadwords' => 'google',
        'googlecom' => 'google',
        'linkedincom' => 'linkedin',
        'lnkdin' => 'linkedin',
        'youtubecom' => 'youtube',
        'yt' => 'youtube',
        'pinterestcom' => 'pinterest',
        'tiktokcom' => 'tiktok',
        'xingcom' => 'xing',
        'bingads' => 'bing',
        'microsoftads' => 'bing',
    ];

    /** The canonical source for a value, null when it is no known alias (a canonical value maps to itself). */
    public static function canonical(string $value): ?string
    {
        $normalized = ValueRules::normalize($value);
        if (isset(self::MAP[$normalized])) {
            return self::MAP[$normalized];
        }
        return in_array($normalized, self::MAP, true) ? $normalized : null;
    }
}
