// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { describe, it, expect, beforeEach } from 'vitest';
import { nextTick } from 'vue';
import { createRouter, createMemoryHistory } from 'vue-router';
import { installTracking } from '@/lib/tracking/index.js';

const Stub = { template: '<div />' };
const matomo = { provider: 'matomo', url: 'https://matomo.example.org/', site_id: '7' };

function makeRouter() {
    return createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/', component: Stub },
            { path: '/tiles', component: Stub },
            { path: '/embed/tiles/:slug', component: Stub, meta: { embed: true } },
        ],
    });
}

const matomoScripts = () => document.querySelectorAll('script[src="https://matomo.example.org/matomo.js"]');
const pageViews = () => window._paq.filter(([command]) => command === 'setCustomUrl').map(([, url]) => url);

describe('installTracking', () => {
    beforeEach(() => {
        // Keep happy-dom from fetching the Matomo script over the network.
        window.happyDOM.settings.disableJavaScriptFileLoading = true;
        delete window._paq;
        matomoScripts().forEach((el) => el.remove());
    });

    it('loads nothing without a tracking configuration', async () => {
        const router = makeRouter();
        installTracking(router, undefined);

        await router.push('/tiles');
        await nextTick();

        expect(matomoScripts()).toHaveLength(0);
        expect(window._paq).toBeUndefined();
    });

    it('counts each SPA navigation with cookieless Matomo, without query strings', async () => {
        const router = makeRouter();
        installTracking(router, matomo);

        await router.push('/');
        await nextTick();
        await router.push('/tiles?category=energy#top');
        await nextTick();

        expect(matomoScripts()).toHaveLength(1);
        expect(window._paq).toContainEqual(['disableCookies']);
        expect(window._paq).toContainEqual(['setDoNotTrack', true]);
        expect(window._paq).toContainEqual(['setTrackerUrl', 'https://matomo.example.org/matomo.php']);
        expect(window._paq).toContainEqual(['setSiteId', '7']);
        expect(pageViews()).toEqual([`${window.location.origin}/`, `${window.location.origin}/tiles`]);
        expect(window._paq.filter(([command]) => command === 'trackPageView')).toHaveLength(2);
    });

    it('does not load or count anything on embedded tiles', async () => {
        const router = makeRouter();
        installTracking(router, matomo);

        await router.push('/embed/tiles/co2');
        await nextTick();

        expect(matomoScripts()).toHaveLength(0);
        expect(window._paq).toBeUndefined();
    });
});
