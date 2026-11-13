<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\Date;
use Piwik\Piwik;
use Piwik\Plugins\FeinwerkCampaignScore\Data\ConventionExport;
use Piwik\Plugins\FeinwerkCampaignScore\Promo\PromoPlacements;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\CorrectionsCsv;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\FindingTexts;

class Controller extends \Piwik\Plugin\Controller
{
    /** The context box of the Feinwerk promotion kit at the end of matching Matomo report pages. */
    public function promoBox()
    {
        return PromoPlacements::box(Common::getRequestVar(PromoPlacements::BOX_PARAMETER, '', 'string'));
    }

    /** The correction list of the selected website and period as a CSV file (view access). */
    public function downloadCorrections()
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');
        Piwik::checkUserHasViewAccess($idSite);

        $table = Request::processRequest('FeinwerkCampaignScore.getCorrections', $this->apiParameters($idSite), []);
        $translate = [Piwik::class, 'translate'];
        $rows = [];
        foreach ($table instanceof DataTable ? $table->getRows() : [] as $row) {
            $rows[] = [
                (string) $row->getColumn('label'),
                FindingTexts::field((string) $row->getMetadata('field'), $translate),
                FindingTexts::type((string) $row->getMetadata('type'), $translate),
                (int) $row->getColumn('nb_visits'),
                (string) $row->getMetadata('suggestion'),
            ];
        }
        $csv = CorrectionsCsv::build([
            Piwik::translate('FeinwerkCampaignScore_ColumnValue'),
            Piwik::translate('FeinwerkCampaignScore_ColumnField'),
            Piwik::translate('FeinwerkCampaignScore_ColumnProblem'),
            Piwik::translate('General_ColumnNbVisits'),
            Piwik::translate('FeinwerkCampaignScore_ColumnSuggestion'),
        ], $rows);

        $this->sendDownloadHeaders('text/csv; charset=utf-8', CorrectionsCsv::fileName($idSite, Date::today()->toString()));
        return $csv;
    }

    /** The starting convention for Feinwerk Campaigns as a JSON file (view access); counts only. */
    public function downloadConvention()
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');
        Piwik::checkUserHasViewAccess($idSite);

        $convention = Request::processRequest('FeinwerkCampaignScore.getConvention', $this->apiParameters($idSite), []);

        $this->sendDownloadHeaders('application/json; charset=utf-8', ConventionExport::fileName($idSite, Date::today()->toString()));
        return (string) json_encode($convention, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @return array<string, mixed> */
    private function apiParameters(int $idSite): array
    {
        return [
            'idSite' => $idSite,
            'period' => Common::getRequestVar('period', 'day', 'string'),
            'date' => Common::getRequestVar('date', 'yesterday', 'string'),
            'segment' => (string) Request::getRawSegmentFromRequest(),
        ];
    }

    private function sendDownloadHeaders(string $contentType, string $fileName): void
    {
        Common::sendHeader('Content-Type: ' . $contentType);
        Common::sendHeader('Content-Disposition: attachment; filename="' . $fileName . '"');
        Common::sendHeader('X-Content-Type-Options: nosniff');
    }
}
