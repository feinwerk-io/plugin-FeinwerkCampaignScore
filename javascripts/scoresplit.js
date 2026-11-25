/*!
 * Matomo - free/libre analytics platform
 * @link https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/*
 * The banner place beside the Campaign Score card pauses through the kit since 6.2.0 / 5.7.0 (javascripts/promocard.js 5,
 * pausable place "fwcs-guide"). 6.1.0 and 5.6.0 kept the pause in localStorage fwcs:bannerplace:paused; this carries
 * such a pause over to the kit's key once, so a place closed before the update stays empty for the rest of its 30 days.
 * Runs at load, before the report arrives. Remove it once no supported version writes the old key (from 6.3.0 / 5.8.0).
 */
(function () {
    'use strict';

    var OLD = 'fwcs:bannerplace:paused';
    var KIT = 'fwpromo:place:fwcs-guide:paused';
    try {
        var since = window.localStorage.getItem(OLD);
        if (since) {
            if (!window.localStorage.getItem(KIT)) {
                window.localStorage.setItem(KIT, since);
            }
            window.localStorage.removeItem(OLD);
        }
    } catch (e) {
        // blocked storage: nothing was stored either
    }
})();
