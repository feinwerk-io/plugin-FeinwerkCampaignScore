/*!
 * Matomo - free/libre analytics platform
 * @link https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/*
 * The banner place beside the Campaign Score card (templates/_scoreSplit.twig): one x hides the whole place for its
 * pause days (Peter, 2026-11-24: "Ein × blendet alles 30 Tage aus"), and only then the next banner of the rotation shows.
 * The rotation itself stays the kit's (javascripts/promocard.js: the x hides that banner for its own, longer days and
 * readies the next one); this script only keeps the place empty during the pause, so the score card takes the width.
 *
 * - The pause starts with a click on an x inside .fwcs-split__side[data-fwcs-pause-days]; localStorage
 *   fwcs:bannerplace:paused holds its time. Blocked storage: paused on this page only.
 * - Demo mode (test instances: the banners carry data-fwpromo-demo) has no pause attribute: every banner in turn, as the
 *   kit's demo mode shows them.
 * - The report arrives after the page: new places are prepared through a MutationObserver. Nothing is loaded or sent.
 * Plain script without a build and without jQuery, for Matomo 5.0 to 6.x.
 */
(function () {
    'use strict';

    if (window.fwcsScoreSplit) {
        return;
    }
    window.fwcsScoreSplit = true;

    var KEY = 'fwcs:bannerplace:paused';
    var SIDE = '.fwcs-split__side[data-fwcs-pause-days]';
    var DAY = 86400000;
    var pausedOnThisPage = false;

    function pauseDays(side) {
        var days = parseInt(side.getAttribute('data-fwcs-pause-days'), 10);
        return days > 0 ? days : 0;
    }

    function pausedSince() {
        try {
            var value = parseInt(window.localStorage.getItem(KEY) || '', 10);
            return value > 0 ? value : 0;
        } catch (e) {
            return 0;
        }
    }

    function isPaused(side) {
        var days = pauseDays(side);
        if (!days) {
            return false;
        }
        if (pausedOnThisPage) {
            return true;
        }
        var since = pausedSince();

        return since > 0 && Date.now() - since < days * DAY;
    }

    function apply(side) {
        var split = side.closest('.fwcs-split');
        if (split && isPaused(side)) {
            split.classList.add('fwcs-split--paused');
        }
    }

    function prepare(root) {
        if (!root || !root.querySelectorAll) {
            return;
        }
        if (root.matches && root.matches(SIDE)) {
            apply(root);
        }
        Array.prototype.forEach.call(root.querySelectorAll(SIDE), apply);
    }

    // capture phase at the window, before the kit's handler (capture at the document, which stops the event there)
    // readies the next banner: the place is already marked as paused
    window.addEventListener('click', function (event) {
        var close = event.target && event.target.closest ? event.target.closest('[data-fwpromo-close]') : null;
        var side = close ? close.closest(SIDE) : null;
        if (!side || !pauseDays(side)) {
            return;
        }
        pausedOnThisPage = true;
        try {
            window.localStorage.setItem(KEY, String(Date.now()));
        } catch (e) {
            // blocked storage: paused on this page only
        }
        apply(side);
        // the kit moves the focus to the next banner, which the pause hides: keep the keyboard on the score card instead
        // (release review 2026-11-24)
        var split = side.closest('.fwcs-split');
        window.setTimeout(function () {
            var active = document.activeElement;
            var main = split ? split.querySelector('.fwcs-split__main') : null;
            if (main && (!active || active === document.body || side.contains(active))) {
                if (!main.hasAttribute('tabindex')) {
                    main.setAttribute('tabindex', '-1');
                }
                main.focus();
            }
        }, 0);
    }, true);

    function start() {
        prepare(document);
        if (!window.MutationObserver) {
            return;
        }
        new window.MutationObserver(function (records) {
            records.forEach(function (record) {
                Array.prototype.forEach.call(record.addedNodes, function (node) {
                    if (node.nodeType === 1) {
                        prepare(node);
                    }
                });
            });
        }).observe(document.documentElement, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
