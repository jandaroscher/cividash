// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { createMatomoTracker } from './matomo.js';

// A further provider is one adapter with trackPageView(url, title) plus its admin settings.
const providers = {
    matomo: createMatomoTracker,
};

/**
 * Counts SPA page views with the provider the tenant configured
 * (window.__TRACKING__, rendered by SpaController). Does nothing without one.
 */
export function installTracking(router, config = window.__TRACKING__) {
    const createTracker = providers[config?.provider];
    if (!createTracker) {
        return;
    }

    let tracker = null;
    let flushPending = null;

    router.afterEach((to, from, failure) => {
        // Embedded tiles run on third-party sites and are not counted; hash or query
        // changes on the same page are not a new page view.
        if (failure || to.meta?.embed || (from.matched.length && to.path === from.path)) {
            return;
        }

        // Created on the first counted view, so embed pages never load the script.
        tracker ??= createTracker(config);

        // A view still waiting for its title is sent now, before the new page changes it.
        flushPending?.();

        // Path only: query strings and hashes are not sent, like the access log.
        const url = window.location.origin + to.path;
        flushPending = afterTitleChange(() => tracker.trackPageView(url, document.title));
    });
}

/**
 * Pages set their title once their API data has arrived, so wait for the title to change
 * (or at most TITLE_WAIT_MS for pages that keep it) before reading it. Returns a function
 * that runs the callback at once if it has not run yet.
 */
const TITLE_WAIT_MS = 1500;

function afterTitleChange(callback) {
    const initialTitle = document.title;
    let done = false;
    const finish = () => {
        if (done) {
            return;
        }
        done = true;
        observer.disconnect();
        clearTimeout(timer);
        callback();
    };
    const observer = new MutationObserver(() => {
        if (document.title !== initialTitle) {
            finish();
        }
    });
    observer.observe(document.head, { subtree: true, childList: true, characterData: true });
    const timer = setTimeout(finish, TITLE_WAIT_MS);

    return finish;
}
