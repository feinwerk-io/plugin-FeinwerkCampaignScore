/*!
 * Matomo - free/libre analytics platform
 * @link https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/*
 * Feedback question under the Campaign Score report and in its dashboard widget (Feinwerk feedback kit,
 * FeinwerkEmailReportsPro/docs/feedback-kit.md; markup in templates/_feedback.twig): thumbs up or down go to the API
 * method sendFeedback, the Matomo server talks to Feinwerk, never the browser. Plain script without a build and
 * without jQuery. Reports arrive and reload after the page (widgets, sorting, goal views), so new bars are prepared
 * through a MutationObserver and clicks are caught at the document.
 */
(function () {
    'use strict';

    var PLUGIN = 'FeinwerkCampaignScore';

    /** localStorage: this browser's vote per question and plugin version (a convenience only) */
    function storageKey(topic) {
        return 'fwcs.feedback.' + topic;
    }

    function storedVote(topic, version) {
        try {
            var stored = JSON.parse(window.localStorage.getItem(storageKey(topic)) || '{}');
            return stored.version === version && (stored.vote === 'up' || stored.vote === 'down') ? stored.vote : '';
        } catch (e) {
            return '';
        }
    }

    function show(bar, vote) {
        Array.prototype.forEach.call(bar.querySelectorAll('.fwcs-feedback__thumb'), function (thumb) {
            var chosen = thumb.getAttribute('data-vote') === vote;
            thumb.disabled = true;
            thumb.classList.toggle('is-chosen', chosen);
            thumb.setAttribute('aria-pressed', chosen ? 'true' : 'false');
        });
        var thanks = bar.querySelector('.fwcs-feedback__thanks');
        if (thanks) {
            thanks.textContent = bar.getAttribute(vote === 'up' ? 'data-thanks-up' : 'data-thanks-down') || '';
        }
        // one line: the thanks takes the place of question and thumbs (stylesheets/feedback.css)
        bar.classList.add('is-voted');
    }

    function prepare() {
        Array.prototype.forEach.call(document.querySelectorAll('.fwcs-feedback:not([data-fwcs-ready])'), function (bar) {
            bar.setAttribute('data-fwcs-ready', '1');
            var vote = storedVote(bar.getAttribute('data-topic'), bar.getAttribute('data-version'));
            if (vote) {
                show(bar, vote);
            }
        });
    }

    function send(bar, vote) {
        var topic = bar.getAttribute('data-topic');
        // every bar with this question on the page (e.g. the same report on a dashboard twice)
        Array.prototype.forEach.call(document.querySelectorAll('.fwcs-feedback[data-topic="' + topic + '"]'), function (other) {
            show(other, vote);
        });
        try {
            window.localStorage.setItem(storageKey(topic), JSON.stringify({version: bar.getAttribute('data-version'), vote: vote}));
        } catch (e) {
            // private mode or blocked storage: the thanks is shown anyway
        }
        var matomo = window.piwik || {};
        var body = new URLSearchParams();
        body.append('token_auth', matomo.token_auth || '');
        body.append('force_api_session', '1');
        body.append('idSite', bar.getAttribute('data-id-site') || '');
        body.append('topic', topic);
        body.append('vote', vote);
        // Best effort: the vote is a hint for Feinwerk, a failed request needs no error message.
        window.fetch('index.php?module=API&format=json&method=' + PLUGIN + '.sendFeedback', {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        }).catch(function () {});
    }

    document.addEventListener('click', function (event) {
        var thumb = event.target && event.target.closest ? event.target.closest('.fwcs-feedback__thumb') : null;
        if (!thumb || thumb.disabled) {
            return;
        }
        var bar = thumb.closest('.fwcs-feedback');
        if (bar) {
            event.preventDefault();
            send(bar, thumb.getAttribute('data-vote'));
        }
    });

    function start() {
        prepare();
        if (!window.MutationObserver) {
            return;
        }
        var scheduled = false;
        new window.MutationObserver(function () {
            if (scheduled) {
                return;
            }
            scheduled = true;
            window.requestAnimationFrame(function () {
                scheduled = false;
                prepare();
            });
        }).observe(document.body, {childList: true, subtree: true});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
