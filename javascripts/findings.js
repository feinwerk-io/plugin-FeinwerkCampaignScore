/*!
 * Matomo - free/libre analytics platform
 * @link https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/*
 * The filter above the findings table of Campaign Score (Ui/FindingTexts::filter): a chip reloads the table with the
 * API parameter findingType (empty: all findings), from the first page. The table keeps the parameter while paging and
 * sorting; the reloaded table shows the pressed chip. Plain script without a build, for Matomo 5.0 to 6.x.
 */
(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        var chip = event.target && event.target.closest ? event.target.closest('.fwcs-filter__chip') : null;
        if (!chip || !window.jQuery) {
            return;
        }
        var element = chip.closest('div.dataTable');
        var dataTable = element ? window.jQuery(element).data('uiControlObject') : null;
        if (!dataTable || !dataTable.param || typeof dataTable.reloadAjaxDataTable !== 'function') {
            return;
        }
        event.preventDefault();
        if (chip.getAttribute('aria-pressed') === 'true') {
            return;
        }
        // an empty value is left out of the request: all findings
        var type = chip.getAttribute('data-fwcs-type') || '';
        dataTable.param.findingType = type;
        dataTable.param.filter_offset = 0;
        // the keyboard focus stays on the chip of the same type in the reloaded table (UI review 2026-11-26 F5)
        dataTable.reloadAjaxDataTable(true, function (response) {
            dataTable.dataTableLoaded(response, dataTable.workingDivId);
            var root = document.getElementById(dataTable.workingDivId) || document;
            var chips = root.querySelectorAll('.fwcs-filter__chip');
            for (var i = 0; i < chips.length; i++) {
                if ((chips[i].getAttribute('data-fwcs-type') || '') === type) {
                    chips[i].focus();
                    break;
                }
            }
        });
    });
})();
