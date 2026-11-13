<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Ui;

/**
 * The correction list as a CSV file: UTF-8 with a byte order mark (spreadsheet programs then read umlauts right),
 * values as people wrote them (Matomo's HTML encoding undone), and cells a spreadsheet would run as a formula
 * (starting with =, +, -, @, tab or carriage return) escaped with an apostrophe.
 */
final class CorrectionsCsv
{
    /**
     * @param list<string> $header
     * @param list<list<string|int|float>> $rows
     */
    public static function build(array $header, array $rows): string
    {
        $lines = [self::line($header)];
        foreach ($rows as $row) {
            $lines[] = self::line($row);
        }
        return "\u{FEFF}" . implode("\n", $lines) . "\n";
    }

    public static function fileName(int $idSite, string $day): string
    {
        return 'campaign-score-corrections-' . $idSite . '-' . $day . '.csv';
    }

    /**
     * @param list<string|int|float> $cells
     */
    private static function line(array $cells): string
    {
        return implode(',', array_map([self::class, 'cell'], $cells));
    }

    /**
     * @param string|int|float $value
     */
    private static function cell($value): string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($value !== '' && strpos("=+-@\t\r", $value[0]) !== false) {
            $value = "'" . $value;
        }
        if (preg_match('/[\s,;"]/', $value)) {
            $value = '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}
