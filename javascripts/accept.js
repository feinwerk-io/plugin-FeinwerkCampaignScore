/*!
 * Matomo - free/libre analytics platform
 * @link https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/*
 * Row action "Accept value" in the findings table of Campaign Score: admins of the website mark the values of a
 * finding as accepted (API method acceptValues, website setting "Accepted values"); they then count as clean. Shown
 * only where the report says so (custom parameter fwcs_can_accept) and for rows with values (row metadata "accept").
 * Plain script without a build, for Matomo 5.0 to 6.x.
 */
(function () {
    'use strict';

    if (typeof window.DataTable_RowActions_Registry === 'undefined' || typeof window.DataTable_RowAction === 'undefined') {
        return;
    }

    var PLUGIN = 'FeinwerkCampaignScore';
    var NAME = 'FwcsAccept';

    function translate(key, args) {
        return typeof window._pk_translate === 'function' ? window._pk_translate(PLUGIN + '_' + key, args || []) : key;
    }

    function valuesOfRow(tr) {
        try {
            var metadata = JSON.parse($(tr).attr('data-row-metadata') || '{}');
            return Array.isArray(metadata.accept) ? metadata.accept : [];
        } catch (e) {
            return [];
        }
    }

    function decode(text) {
        // Matomo stores tracked values HTML-encoded; show them as people wrote them (DOMParser runs no scripts, and the
        // result is only used as plain text in the confirmation dialog)
        try {
            return new window.DOMParser().parseFromString(String(text), 'text/html').documentElement.textContent || '';
        } catch (e) {
            return String(text);
        }
    }

    function AcceptAction(dataTable) {
        this.dataTable = dataTable;
        this.trEventName = 'fwcsAcceptRowAction';
    }

    AcceptAction.prototype = new window.DataTable_RowAction();

    AcceptAction.prototype.trigger = function (tr) {
        var values = valuesOfRow(tr);
        if (!values.length) {
            return;
        }
        var shown = values.map(decode).join(', ');
        if (!window.confirm(translate('AcceptConfirm', [shown]))) {
            return;
        }
        var matomo = window.piwik || {};
        var body = new URLSearchParams();
        body.append('token_auth', matomo.token_auth || '');
        body.append('force_api_session', '1');
        body.append('idSite', String(matomo.idSite || ''));
        body.append('values', values.join('\n'));

        window.fetch('index.php?module=API&format=json&method=' + PLUGIN + '.acceptValues', {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        }).then(function (answer) {
            if (!answer || answer.result === 'error') {
                window.alert(answer && answer.message ? answer.message : 'Error');
                return;
            }
            window.alert(translate('AcceptDone'));
            // score card and findings change together: load the page again
            window.location.reload();
        }).catch(function (error) {
            window.alert(String(error && error.message ? error.message : error));
        });
    };

    window.DataTable_RowActions_Registry.register({
        name: NAME,
        dataTableIcon: 'icon-ok',
        order: 25,
        dataTableIconTooltip: [translate('AcceptRowAction'), translate('AcceptRowActionTooltip')],
        isAvailableOnReport: function (dataTableParams) {
            return dataTableParams
                && dataTableParams.module === PLUGIN
                && dataTableParams.action === 'getFindings'
                && String(dataTableParams.fwcs_can_accept) === '1';
        },
        isAvailableOnRow: function (dataTableParams, tr) {
            return valuesOfRow(tr).length > 0;
        },
        createInstance: function (dataTable) {
            return new AcceptAction(dataTable);
        }
    });
})();
