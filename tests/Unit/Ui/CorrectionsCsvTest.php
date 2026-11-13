<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\tests\Unit\Ui;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\CorrectionsCsv;

/**
 * The correction list as CSV: readable values, safe in spreadsheet programs.
 *
 * @group FeinwerkCampaignScore
 */
class CorrectionsCsvTest extends TestCase
{
    public function test_oneLinePerValueWithAHeader(): void
    {
        $csv = CorrectionsCsv::build(['Value', 'Field', 'Problem', 'Visits', 'Suggestion'], [
            ['Summer &amp; Sale', 'Campaign name', 'Format', 12, 'summer-sale'],
        ]);

        $this->assertSame("\u{FEFF}Value,Field,Problem,Visits,Suggestion\n\"Summer & Sale\",\"Campaign name\",Format,12,summer-sale\n", $csv);
    }

    public function test_cellsThatASpreadsheetWouldRunAsAFormula_areEscaped(): void
    {
        $csv = CorrectionsCsv::build(['Value'], [['=HYPERLINK("x")'], ['+1'], ['-sale'], ['@cmd'], ['black-friday']]);

        $this->assertStringContainsString("\"'=HYPERLINK(\"\"x\"\")\"\n", $csv);
        $this->assertStringContainsString("'+1\n", $csv);
        $this->assertStringContainsString("'-sale\n", $csv);
        $this->assertStringContainsString("'@cmd\n", $csv);
        $this->assertStringContainsString("\nblack-friday\n", $csv);
    }

    public function test_fileName(): void
    {
        $this->assertSame('campaign-score-corrections-3-2026-11-13.csv', CorrectionsCsv::fileName(3, '2026-11-13'));
    }
}
