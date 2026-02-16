import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useFooterStore } from '@/stores/footer';
import { mockFetch, mockFetchError } from '../setup';

describe('footerStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.restoreAllMocks();
    });

    describe('initial state', () => {
        it('has correct default values', () => {
            const store = useFooterStore();
            expect(store.footerNavigationItems).toEqual([]);
            expect(store.socialLinks).toEqual([]);
            expect(store.layoutType).toBe('columns');
            expect(store.columns).toBe(4);
            expect(store.socialLinksEnabled).toBe(false);
            expect(store.copyrightText).toBeNull();
            expect(store.loading).toBe(false);
            expect(store.error).toBeNull();
        });
    });

    describe('getters', () => {
        it('isLoading returns loading state', () => {
            const store = useFooterStore();
            expect(store.isLoading).toBe(false);
            store.loading = true;
            expect(store.isLoading).toBe(true);
        });

        it('getFooterNavigationItems returns footerNavigationItems', () => {
            const store = useFooterStore();
            const items = [
                { id: 1, label: 'Impressum', url: '/impressum' },
                { id: 2, label: 'Datenschutz', url: '/datenschutz' },
            ];
            store.footerNavigationItems = items;
            expect(store.getFooterNavigationItems).toEqual(items);
        });

        it('getFooterNavigationItems returns empty array by default', () => {
            const store = useFooterStore();
            expect(store.getFooterNavigationItems).toEqual([]);
        });

        it('getSocialLinks returns socialLinks', () => {
            const store = useFooterStore();
            const links = [
                { platform: 'twitter', url: 'https://twitter.com/example' },
                { platform: 'github', url: 'https://github.com/example' },
            ];
            store.socialLinks = links;
            expect(store.getSocialLinks).toEqual(links);
        });

        it('getSocialLinks returns empty array by default', () => {
            const store = useFooterStore();
            expect(store.getSocialLinks).toEqual([]);
        });
    });

    describe('fetchConfig', () => {
        const fullFooterResponse = {
            data: {
                footer_navigation_items: [
                    { id: 1, label: 'Impressum', url: '/impressum' },
                    { id: 2, label: 'Datenschutz', url: '/datenschutz' },
                ],
                social_links: [
                    { platform: 'twitter', url: 'https://twitter.com/example' },
                ],
                layout_type: 'simple',
                columns: 3,
                social_links_enabled: true,
                copyright_text: '2024 Example GmbH',
            },
        };

        it('fetches footer config and sets all fields', async () => {
            globalThis.fetch = mockFetch(fullFooterResponse);

            const store = useFooterStore();
            const result = await store.fetchConfig('de');

            expect(store.footerNavigationItems).toEqual(fullFooterResponse.data.footer_navigation_items);
            expect(store.socialLinks).toEqual(fullFooterResponse.data.social_links);
            expect(store.layoutType).toBe('simple');
            expect(store.columns).toBe(3);
            expect(store.socialLinksEnabled).toBe(true);
            expect(store.copyrightText).toBe('2024 Example GmbH');
            expect(store.error).toBeNull();
            expect(result).toBeTruthy();
        });

        it('calls API with correct URL and credentials', async () => {
            globalThis.fetch = mockFetch(fullFooterResponse);

            const store = useFooterStore();
            await store.fetchConfig('de');

            expect(globalThis.fetch).toHaveBeenCalledWith(
                expect.stringContaining('/api/config/footer?locale=de'),
                expect.objectContaining({ credentials: 'include' }),
            );
        });

        it('uses locale parameter in API call', async () => {
            globalThis.fetch = mockFetch(fullFooterResponse);

            const store = useFooterStore();
            await store.fetchConfig('en');

            expect(globalThis.fetch).toHaveBeenCalledWith(
                expect.stringContaining('/api/config/footer?locale=en'),
                expect.any(Object),
            );
        });

        it('defaults locale to de', async () => {
            globalThis.fetch = mockFetch(fullFooterResponse);

            const store = useFooterStore();
            await store.fetchConfig();

            expect(globalThis.fetch).toHaveBeenCalledWith(
                expect.stringContaining('locale=de'),
                expect.any(Object),
            );
        });

        it('sets error on fetch failure', async () => {
            vi.spyOn(console, 'error').mockImplementation(() => {});
            globalThis.fetch = mockFetchError('Network error');

            const store = useFooterStore();
            const result = await store.fetchConfig('de');

            expect(result).toBeNull();
            expect(store.error).toBeInstanceOf(Error);
            expect(store.error.message).toBe('Network error');
            expect(store.loading).toBe(false);
        });

        it('sets error on non-ok response', async () => {
            vi.spyOn(console, 'error').mockImplementation(() => {});
            globalThis.fetch = mockFetch(null, { ok: false, status: 500 });

            const store = useFooterStore();
            const result = await store.fetchConfig('de');

            expect(result).toBeNull();
            expect(store.error).toBeInstanceOf(Error);
            expect(store.error.message).toContain('500');
        });

        it('toggles loading state during fetch', async () => {
            let resolvePromise;
            globalThis.fetch = vi.fn().mockReturnValue(
                new Promise((resolve) => {
                    resolvePromise = resolve;
                }),
            );

            const store = useFooterStore();
            const fetchPromise = store.fetchConfig('de');

            expect(store.loading).toBe(true);

            resolvePromise({
                ok: true,
                status: 200,
                json: () => Promise.resolve(fullFooterResponse),
            });
            await fetchPromise;

            expect(store.loading).toBe(false);
        });

        it('defaults missing fields to safe values', async () => {
            globalThis.fetch = mockFetch({ data: {} });

            const store = useFooterStore();
            await store.fetchConfig('de');

            expect(store.footerNavigationItems).toEqual([]);
            expect(store.socialLinks).toEqual([]);
            expect(store.layoutType).toBe('columns');
            expect(store.columns).toBe(4);
            expect(store.socialLinksEnabled).toBe(false);
            expect(store.copyrightText).toBeNull();
        });

        it('clears previous error on new fetch', async () => {
            vi.spyOn(console, 'error').mockImplementation(() => {});

            // First call fails
            globalThis.fetch = mockFetchError('Network error');
            const store = useFooterStore();
            await store.fetchConfig('de');
            expect(store.error).toBeTruthy();

            // Second call succeeds
            globalThis.fetch = mockFetch(fullFooterResponse);
            await store.fetchConfig('de');
            expect(store.error).toBeNull();
        });

        it('uses json.data when available, falls back to json', async () => {
            const responseData = {
                footer_navigation_items: [{ id: 1, label: 'Test' }],
                social_links: [],
                layout_type: 'simple',
                columns: 2,
                social_links_enabled: false,
                copyright_text: 'Test',
            };
            // Response without .data wrapper
            globalThis.fetch = vi.fn().mockResolvedValue({
                ok: true,
                status: 200,
                statusText: 'OK',
                json: () => Promise.resolve(responseData),
                headers: new Headers(),
            });

            const store = useFooterStore();
            await store.fetchConfig('de');

            expect(store.footerNavigationItems).toEqual([{ id: 1, label: 'Test' }]);
            expect(store.layoutType).toBe('simple');
            expect(store.columns).toBe(2);
            expect(store.copyrightText).toBe('Test');
        });
    });
});
