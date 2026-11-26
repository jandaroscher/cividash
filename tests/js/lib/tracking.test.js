// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
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

let appended;
const matomoScripts = () => appended.filter((el) => el.src === 'https://matomo.example.org/matomo.js');
const commands = (name) => window._paq.filter(([command]) => command === name).map(([, value]) => value);

async function visit(router, path) {
    await router.push(path);
    await nextTick();
}

describe('installTracking', () => {
    beforeEach(() => {
        delete window._paq;
        // Capture the script element instead of letting happy-dom fetch it.
        appended = [];
        vi.spyOn(document.head, 'appendChild').mockImplementation((el) => appended.push(el) && el);
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('loads nothing without a tracking configuration', async () => {
        const router = makeRouter();
        installTracking(router, undefined);

        await visit(router, '/tiles');

        expect(matomoScripts()).toHaveLength(0);
        expect(window._paq).toBeUndefined();
    });

    it('counts each SPA navigation with cookieless Matomo, without query strings', async () => {
        const router = makeRouter();
        installTracking(router, matomo);

        await visit(router, '/');
        await visit(router, '/tiles?category=energy#top');

        expect(matomoScripts()).toHaveLength(1);
        expect(window._paq).toContainEqual(['disableCookies']);
        expect(window._paq).toContainEqual(['disableBrowserFeatureDetection']);
        expect(window._paq).toContainEqual(['setDoNotTrack', true]);
        expect(window._paq).toContainEqual(['setTrackerUrl', 'https://matomo.example.org/matomo.php']);
        expect(window._paq).toContainEqual(['setSiteId', '7']);
        expect(commands('setCustomUrl')).toEqual([`${window.location.origin}/`, `${window.location.origin}/tiles`]);
        expect(commands('setReferrerUrl')).toEqual([`${window.location.origin}/`]);
        expect(window._paq.filter(([command]) => command === 'trackPageView')).toHaveLength(2);
    });

    it('keeps counting after matomo.js has replaced the _paq queue', async () => {
        const router = makeRouter();
        installTracking(router, matomo);
        await visit(router, '/');

        // What the loaded matomo.js does: swap the array for an object that runs commands.
        const executed = [];
        window._paq = { push: (command) => executed.push(command) };
        await visit(router, '/tiles');

        expect(executed).toContainEqual(['setCustomUrl', `${window.location.origin}/tiles`]);
        expect(executed).toContainEqual(['trackPageView']);
    });

    it('does not count hash or query changes on the same page again', async () => {
        const router = makeRouter();
        installTracking(router, matomo);

        await visit(router, '/tiles');
        await visit(router, '/tiles#energy');
        await visit(router, '/tiles?x=1');

        expect(commands('setCustomUrl')).toEqual([`${window.location.origin}/tiles`]);
    });

    it('strips the query string from a same-origin referrer on the first view', async () => {
        vi.spyOn(document, 'referrer', 'get').mockReturnValue(`${window.location.origin}/tiles?category=energy`);
        const router = makeRouter();
        installTracking(router, matomo);

        await visit(router, '/');

        expect(commands('setReferrerUrl')).toEqual([`${window.location.origin}/tiles`]);
    });

    it('does not load or count anything on embedded tiles', async () => {
        const router = makeRouter();
        installTracking(router, matomo);

        await visit(router, '/embed/tiles/co2');

        expect(matomoScripts()).toHaveLength(0);
        expect(window._paq).toBeUndefined();
    });
});
