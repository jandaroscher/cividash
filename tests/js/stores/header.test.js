import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useHeaderStore } from '@/stores/header';
import { mockFetch, mockFetchError } from '../setup';

describe('headerStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.restoreAllMocks();
    });

    describe('initial state', () => {
        it('has correct default values', () => {
            const store = useHeaderStore();
            expect(store.navigationItems).toEqual([]);
            expect(store.dropdownEnabled).toBe(false);
            expect(store.loading).toBe(false);
            expect(store.error).toBeNull();
        });
    });

    describe('getters', () => {
        it('isLoading returns loading state', () => {
            const store = useHeaderStore();
            expect(store.isLoading).toBe(false);
            store.loading = true;
            expect(store.isLoading).toBe(true);
        });

        it('getNavigationItems returns navigationItems', () => {
            const store = useHeaderStore();
            const items = [
                { id: 1, label: 'Home', url: '/' },
                { id: 2, label: 'About', url: '/about' },
            ];
            store.navigationItems = items;
            expect(store.getNavigationItems).toEqual(items);
        });

        it('getNavigationItems returns empty array by default', () => {
            const store = useHeaderStore();
            expect(store.getNavigationItems).toEqual([]);
        });
    });

    describe('fetchConfig', () => {
        it('fetches header config and sets navigation items', async () => {
            const navItems = [
                { id: 1, label: 'Home', url: '/' },
                { id: 2, label: 'Tiles', url: '/tiles' },
            ];
            globalThis.fetch = mockFetch({
                data: {
                    navigation_items: navItems,
                    dropdown_enabled: true,
                },
            });

            const store = useHeaderStore();
            const result = await store.fetchConfig('de');

            expect(store.navigationItems).toEqual(navItems);
            expect(store.dropdownEnabled).toBe(true);
            expect(store.error).toBeNull();
            expect(result).toBeTruthy();
            expect(globalThis.fetch).toHaveBeenCalledWith(
                expect.stringContaining('/api/config/header?locale=de'),
                expect.objectContaining({ credentials: 'include' }),
            );
        });

        it('uses locale parameter in API call', async () => {
            globalThis.fetch = mockFetch({
                data: {
                    navigation_items: [],
                    dropdown_enabled: false,
                },
            });

            const store = useHeaderStore();
            await store.fetchConfig('en');

            expect(globalThis.fetch).toHaveBeenCalledWith(
                expect.stringContaining('/api/config/header?locale=en'),
                expect.any(Object),
            );
        });

        it('defaults locale to de', async () => {
            globalThis.fetch = mockFetch({
                data: {
                    navigation_items: [],
                    dropdown_enabled: false,
                },
            });

            const store = useHeaderStore();
            await store.fetchConfig();

            expect(globalThis.fetch).toHaveBeenCalledWith(
                expect.stringContaining('locale=de'),
                expect.any(Object),
            );
        });

        it('sets error message on fetch failure', async () => {
            vi.spyOn(console, 'error').mockImplementation(() => {});
            globalThis.fetch = mockFetchError('Network error');

            const store = useHeaderStore();
            const result = await store.fetchConfig('de');

            expect(result).toBeNull();
            expect(store.error).toBe('Network error');
            expect(store.loading).toBe(false);
        });

        it('sets error message on non-ok response', async () => {
            vi.spyOn(console, 'error').mockImplementation(() => {});
            globalThis.fetch = mockFetch(null, { ok: false, status: 500 });

            const store = useHeaderStore();
            const result = await store.fetchConfig('de');

            expect(result).toBeNull();
            expect(store.error).toContain('500');
            expect(store.loading).toBe(false);
        });

        it('toggles loading state during fetch', async () => {
            let resolvePromise;
            globalThis.fetch = vi.fn().mockReturnValue(
                new Promise((resolve) => {
                    resolvePromise = resolve;
                }),
            );

            const store = useHeaderStore();
            const fetchPromise = store.fetchConfig('de');

            expect(store.loading).toBe(true);

            resolvePromise({
                ok: true,
                status: 200,
                json: () =>
                    Promise.resolve({
                        data: {
                            navigation_items: [],
                            dropdown_enabled: false,
                        },
                    }),
            });
            await fetchPromise;

            expect(store.loading).toBe(false);
        });

        it('defaults navigation_items to empty array when missing', async () => {
            globalThis.fetch = mockFetch({ data: {} });

            const store = useHeaderStore();
            await store.fetchConfig('de');

            expect(store.navigationItems).toEqual([]);
        });

        it('defaults dropdownEnabled to false when missing', async () => {
            globalThis.fetch = mockFetch({ data: {} });

            const store = useHeaderStore();
            await store.fetchConfig('de');

            expect(store.dropdownEnabled).toBe(false);
        });

        it('clears previous error on new fetch', async () => {
            vi.spyOn(console, 'error').mockImplementation(() => {});

            // First call fails
            globalThis.fetch = mockFetchError('Network error');
            const store = useHeaderStore();
            await store.fetchConfig('de');
            expect(store.error).toBeTruthy();

            // Second call succeeds
            globalThis.fetch = mockFetch({
                data: {
                    navigation_items: [],
                    dropdown_enabled: false,
                },
            });
            await store.fetchConfig('de');
            expect(store.error).toBeNull();
        });

        it('uses json.data when available, falls back to json', async () => {
            const responseData = {
                navigation_items: [{ id: 1, label: 'Home' }],
                dropdown_enabled: false,
            };
            // Response without .data wrapper
            globalThis.fetch = vi.fn().mockResolvedValue({
                ok: true,
                status: 200,
                statusText: 'OK',
                json: () => Promise.resolve(responseData),
                headers: new Headers(),
            });

            const store = useHeaderStore();
            await store.fetchConfig('de');

            expect(store.navigationItems).toEqual([{ id: 1, label: 'Home' }]);
        });
    });
});
