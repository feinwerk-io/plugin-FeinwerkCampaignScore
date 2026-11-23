/*!
 * Matomo - free/libre analytics platform
 * @link https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/*
 * "Campaign Score of all websites" with many websites: the table is read only on a click on its button (the reports of
 * up to 100 websites), then this box is replaced by the table the widget renders (templates/_allSites.twig). Plain
 * script without a build, for Matomo 5.0 to 6.x.
 */
(function ($) {
    'use strict';

    if (typeof $ === 'undefined') {
        return;
    }

    $(document).on('click', '.fwcs-allsites__load', function () {
        var $button = $(this);
        var $box = $button.closest('.fwcs-allsites');
        var $status = $box.find('.fwcs-allsites__status');

        function fail() {
            $status.text($button.attr('data-error') || '');
            $button.prop('disabled', false);
        }

        function show(html) {
            // the widget's own markup (scripts are not run); found at the top or inside a wrapper
            var $nodes = $($.parseHTML(String(html || '').trim()));
            var $table = $nodes.filter('.fwcs-allsites').add($nodes.find('.fwcs-allsites')).first();
            if ($table.length !== 1 || $table.find('.fwcs-allsites__load').length > 0) {
                fail();
                return;
            }
            $box.replaceWith($table);
        }

        $button.prop('disabled', true);
        $status.text($button.attr('data-loading') || '');

        // Matomo's request helper adds website, period, date and segment of the page and, in an embedded dashboard
        // or framed page, the token (there is no session there)
        if (typeof window.ajaxHelper === 'function') {
            var request = new window.ajaxHelper();
            request.addParams({module: 'FeinwerkCampaignScore', action: 'getAllSites', fwcs_load: 1}, 'get');
            request.setFormat('html');
            request.setCallback(show);
            request.setErrorCallback(fail);
            var sent = request.send();
            if (sent && typeof sent.catch === 'function') {
                sent.catch(function () {});
            }
            return;
        }
        $.ajax({url: $button.attr('data-url'), dataType: 'html', cache: false}).done(show).fail(fail);
    });
})(window.jQuery);
