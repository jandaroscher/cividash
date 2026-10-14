/**
 * @vitest-environment-options {"settings":{"disableIframePageLoading":true}}
 */
import { describe, it, expect, beforeAll } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const ORIGIN = 'https://dash.example';

function dispatchResize(source, height, origin = ORIGIN) {
    window.dispatchEvent(new MessageEvent('message', {
        origin,
        source,
        data: { type: 'cividash:resize', height },
    }));
}

describe('public/embed.js', () => {
    beforeAll(() => {
        Object.defineProperty(document, 'currentScript', {
            configurable: true,
            value: { src: `${ORIGIN}/embed.js` },
        });
        new Function(readFileSync(resolve(__dirname, '../../../public/embed.js'), 'utf8'))();
        delete document.currentScript;
    });

    it('renders an iframe for the tile with an accessible title', () => {
        document.body.innerHTML = '<cividash-tile slug="co2 emissionen" lang="en" label="CO2"></cividash-tile>';
        const iframe = document.querySelector('cividash-tile iframe');

        expect(iframe.src).toBe(`${ORIGIN}/en/embed/tiles/co2%20emissionen`);
        expect(iframe.title).toBe('CO2');
    });

    it('resizes only the iframe that sent the message, and only from the dashboard origin', () => {
        document.body.innerHTML = '<cividash-tile slug="a"></cividash-tile><cividash-tile slug="b"></cividash-tile>';
        const [first, second] = document.querySelectorAll('cividash-tile iframe');
        // Iframe page loading is disabled, so give each frame a distinct window stand-in.
        const firstWindow = {};
        const secondWindow = {};
        Object.defineProperty(first, 'contentWindow', { value: firstWindow });
        Object.defineProperty(second, 'contentWindow', { value: secondWindow });

        dispatchResize(firstWindow, 321);
        dispatchResize(secondWindow, 999, 'https://evil.example');
        dispatchResize(secondWindow, -5);

        expect(first.style.height).toBe('321px');
        expect(second.style.height).toBe('400px');
    });
});
