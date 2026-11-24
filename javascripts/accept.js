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

    // one request at a time (a second click while the first runs does nothing)
    var busy = false;

    function translateCore(key) {
        var text = typeof window._pk_translate === 'function' ? window._pk_translate(key) : key;
        return text && text !== key ? text : key.replace(/^General_/, '');
    }

    // Matomo's own dialog (piwikHelper.modalConfirm follows Matomo's theme, also dark mode; the browser's dialog does
    // not), built once; the text is set as plain text. buttons: [[role, label], ...]
    function dialog(className, text, buttons, callbacks, detail) {
        if (!window.piwikHelper || typeof window.piwikHelper.modalConfirm !== 'function') {
            if (callbacks.yes && buttons.length > 1) {
                if (window.confirm(text + (detail ? '\n\n' + detail : ''))) {
                    callbacks.yes();
                }
            } else {
                window.alert(text);
            }
            return;
        }
        var box = document.querySelector('.' + className);
        if (!box) {
            box = document.createElement('div');
            box.className = 'ui-confirm ' + className;
            box.style.display = 'none';
            var heading = document.createElement('h2');
            box.appendChild(heading);
            var paragraph = document.createElement('p');
            box.appendChild(paragraph);
            buttons.forEach(function (button) {
                var input = document.createElement('input');
                input.type = 'button';
                input.setAttribute('role', button[0]);
                input.value = button[1];
                box.appendChild(input);
            });
            document.body.appendChild(box);
        }
        box.querySelector('h2').textContent = text;
        var p = box.querySelector('p');
        if (p) {
            p.textContent = detail || '';
            p.style.display = detail ? '' : 'none';
        }
        window.piwikHelper.modalConfirm(box, callbacks);
    }

    function showError(message) {
        dialog('fwcs-accept-error', message || translate('AcceptFailed'), [['yes', translateCore('General_Ok')]], {});
    }

    AcceptAction.prototype.trigger = function (tr) {
        var values = valuesOfRow(tr);
        if (!values.length || busy) {
            return;
        }
        var shown = values.map(decode).join(', ');
        // the question as the heading, the explanation as a paragraph, like Matomo's own confirmations
        dialog('fwcs-accept-confirm', translate('AcceptConfirm', [shown]),
            [['yes', translateCore('General_Yes')], ['no', translateCore('General_No')]], {
                yes: function () {
                    send(values);
                }
            }, translate('AcceptConfirmText'));
    };

    function send(values) {
        if (busy) {
            return;
        }
        busy = true;
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
            // the API's message also comes with an error status (HTTP 400 for bad input)
            return response.json();
        }).then(function (answer) {
            if (!answer || answer.result === 'error') {
                busy = false;
                showError(answer && answer.message ? String(answer.message) : '');
                return;
            }
            // score card and findings change together: load the page again
            window.location.reload();
        }).catch(function () {
            busy = false;
            showError('');
        });
    }

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
