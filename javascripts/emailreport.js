/*!
 * Matomo - free/libre analytics platform
 * @link https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/*
 * "Send weekly by email" in the score card of Campaign Score (templates/_scoreCard.twig): creates the user's Campaign
 * Score email report through the API method addEmailReport, then links to Matomo's page of email reports. One request
 * at a time; the result is read out by the status region. Plain script without a build, for Matomo 5.0 to 6.x.
 */
(function () {
    'use strict';

    var PLUGIN = 'FeinwerkCampaignScore';

    function translate(key) {
        return typeof window._pk_translate === 'function' ? window._pk_translate(PLUGIN + '_' + key) : key;
    }

    document.addEventListener('click', function (event) {
        var button = event.target && event.target.closest ? event.target.closest('.fwcs-card__email-btn') : null;
        if (!button || button.getAttribute('aria-busy') === 'true') {
            return;
        }
        event.preventDefault();
        var box = button.closest('.fwcs-card__email');
        var status = box ? box.querySelector('.fwcs-card__email-status') : null;
        var matomo = window.piwik || {};
        var body = new URLSearchParams();
        body.append('token_auth', matomo.token_auth || '');
        body.append('force_api_session', '1');
        body.append('idSite', String(matomo.idSite || ''));
        body.append('period', 'week');
        button.setAttribute('aria-busy', 'true');
        button.disabled = true;

        window.fetch('index.php?module=API&format=json&method=' + PLUGIN + '.addEmailReport', {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        }).then(function (answer) {
            if (!answer || answer.result === 'error' || !answer.url) {
                // only the API's own message is shown; anything else gets the plugin's sentence (UI review W3)
                var failure = new Error('');
                failure.apiMessage = answer && answer.result === 'error' && answer.message ? String(answer.message) : '';
                throw failure;
            }
            // the button becomes the link to Matomo's email reports; the status says what happened
            var link = document.createElement('a');
            link.href = String(answer.url);
            link.textContent = translate('EmailReportManage');
            button.replaceWith(link);
            if (status) {
                status.textContent = translate('EmailReportDone');
            }
            link.focus();
        }).catch(function (error) {
            button.removeAttribute('aria-busy');
            button.disabled = false;
            if (status) {
                status.textContent = (error && error.apiMessage) || translate('EmailReportFailed');
            }
        });
    });
})();
