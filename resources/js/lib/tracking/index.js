// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { nextTick } from 'vue';
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

    router.afterEach((to, from, failure) => {
        // Embedded tiles run on third-party sites and are not counted.
        if (failure || to.meta?.embed) {
            return;
        }

        // Created on the first counted view, so embed pages never load the script.
        tracker ??= createTracker(config);

        // Path only: query strings and hashes are not sent, like the access log.
        // shortcut: pages set their title after their API call, so the title can still be the previous one; track from the pages if titles matter in reports.
        nextTick(() => tracker.trackPageView(window.location.origin + to.path, document.title));
    });
}
