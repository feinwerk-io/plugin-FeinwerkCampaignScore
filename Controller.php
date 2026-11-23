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
use Piwik\Container\StaticContainer;
use Piwik\DataTable;
use Piwik\Date;
use Piwik\Nonce;
use Piwik\Piwik;
use Piwik\Plugin\Manager as PluginManager;
use Piwik\Plugins\FeinwerkCampaignScore\ConnectorInstaller\ConnectorInstaller;
use Piwik\Plugins\FeinwerkCampaignScore\Data\ConventionExport;
use Piwik\Plugins\FeinwerkCampaignScore\Promo\PromoPlacements;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\CorrectionsCsv;
use Piwik\Plugins\FeinwerkCampaignScore\Ui\FindingTexts;
use Piwik\Url;
use Piwik\View;

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

    /**
     * Kit: the Feinwerk Connector installer page (ConnectorInstaller/ConnectorInstaller.php). Super users only.
     */
    public function connectorInstaller()
    {
        Piwik::checkUserHasSuperUserAccess();
        $env = ConnectorInstaller::environment();
        $redirect = ConnectorInstaller::redirectFor($env);
        if ($redirect !== null) {
            Url::redirectToUrl($redirect);

            return '';
        }

        return $this->renderConnectorInstaller($env, null);
    }

    /**
     * Kit: the click. Matomo's password check first (it comes back here by GET with these parameters), then the nonce
     * (verified and discarded, as Matomo's own install action does), then install when the files are missing, and
     * activate. Success: on to the connector; any failure: back on the page with Matomo's reason.
     */
    public function connectorInstallerRun()
    {
        Piwik::checkUserHasSuperUserAccess();
        $env = ConnectorInstaller::environment();
        $redirect = ConnectorInstaller::redirectFor($env);
        if ($redirect !== null) {
            // active: the connector; switched off: admin home (never the page's "open the connector" fallback)
            Url::redirectToUrl($redirect);

            return '';
        }
        $state = ConnectorInstaller::decide($env);
        if ($state !== ConnectorInstaller::INSTALL && $state !== ConnectorInstaller::ACTIVATE) {
            return $this->renderConnectorInstaller($env, null);
        }

        $nonce = Common::getRequestVar('nonce', '', 'string');
        $verified = StaticContainer::get(\Piwik\Plugins\Login\PasswordVerifier::class)->requirePasswordVerifiedRecently([
            'module' => ConnectorInstaller::PLUGIN,
            'action' => 'connectorInstallerRun',
            'nonce' => $nonce,
        ]);
        if (!$verified) {
            // Matomo is sending the user to its password page (it returns only when its redirect is off)
            return '';
        }

        $error = Nonce::verifyNonceWithErrorMessage(ConnectorInstaller::NONCE, $nonce);
        if ($error !== '') {
            // Matomo's own reason, from its translations (may hold its FAQ link)
            return $this->renderConnectorInstaller($env, ['ok' => false, 'message' => $error, 'html' => true]);
        }
        Nonce::discardNonce(ConnectorInstaller::NONCE);

        $result = ConnectorInstaller::run(
            $env['present'],
            static function (): void {
                (new \Piwik\Plugins\CorePluginsAdmin\PluginInstaller())->installOrUpdatePluginFromMarketplace(ConnectorInstaller::CONNECTOR);
            },
            static function (): void {
                PluginManager::getInstance()->activatePlugin(ConnectorInstaller::CONNECTOR);
            }
        );
        if ($result['ok']) {
            Url::redirectToUrl(ConnectorInstaller::CONNECTOR_URL);
        }

        return $this->renderConnectorInstaller(ConnectorInstaller::environment(), $result);
    }

    /**
     * @param array<string, bool> $env
     * @param array{ok: bool, message: string, html: bool}|null $result
     */
    private function renderConnectorInstaller(array $env, ?array $result): string
    {
        $view = new View('@FeinwerkCampaignScore/connectorInstaller');
        // an admin page without a website: setGeneralVariablesView() needs idSite (the menu entry is reached from
        // admin pages without one); the admin variables also send X-Frame-Options as enable_framed_settings says
        $this->setBasicVariablesView($view);
        \Piwik\Plugin\ControllerAdmin::setBasicVariablesAdminView($view);
        $state = ConnectorInstaller::decide($env);
        $view->installerState = $state;
        $view->installerError = $result !== null && !$result['ok'] ? $result : null;
        $view->installerNonce = $state === ConnectorInstaller::INSTALL || $state === ConnectorInstaller::ACTIVATE
            ? Nonce::getNonce(ConnectorInstaller::NONCE)
            : '';
        $view->installerLinks = ConnectorInstaller::manualLinks();
        $view->installerWordPressUrl = ConnectorInstaller::wordpressUrl();
        $view->installerConnectorUrl = ConnectorInstaller::CONNECTOR_URL;
        $view->installerPhpFloor = ConnectorInstaller::PHP_FLOOR;
        $view->installerPluginsAdmin = $env['pluginsAdmin'];
        $view->installerConsoleCommand = ConnectorInstaller::CONSOLE_COMMAND;

        return $view->render();
    }
}
