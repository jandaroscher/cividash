import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useTilesStore } from '@/stores/tiles';
import { mockFetch, mockFetchError } from '../setup';

describe('tilesStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.restoreAllMocks();
    });

    describe('initial state', () => {
        it('has correct default values', () => {
            const store = useTilesStore();
            expect(store.tiles).toEqual([]);
            expect(store.currentTile).toBeNull();
            expect(store.loading).toBe(false);
            expect(store.error).toBeNull();
            expect(store.locale).toBe('de');
            expect(store.cache).toBeInstanceOf(Map);
            expect(store.cache.size).toBe(0);
        });
    });

    describe('setLocale', () => {
        it('sets locale to a valid value', () => {
            const store = useTilesStore();
            store.setLocale('en');
            expect(store.locale).toBe('en');
        });

        it('sets locale back to de', () => {
            const store = useTilesStore();
            store.setLocale('en');
            store.setLocale('de');
            expect(store.locale).toBe('de');
        });

        it('ignores invalid locale values', () => {
            const store = useTilesStore();
            store.setLocale('fr');
            expect(store.locale).toBe('de');
        });

        it('ignores null locale', () => {
            const store = useTilesStore();
            store.setLocale(null);
            expect(store.locale).toBe('de');
        });

        it('ignores undefined locale', () => {
            const store = useTilesStore();
            store.setLocale(undefined);
            expect(store.locale).toBe('de');
        });
    });

    describe('fetchAll', () => {
        it('fetches tiles and sets state on success', async () => {
            const tilesData = [
                { id: 1, title: 'Tile 1', slug: 'tile-1' },
                { id: 2, title: 'Tile 2', slug: 'tile-2' },
            ];
            globalThis.fetch = mockFetch({ data: tilesData });

            const store = useTilesStore();
            await store.fetchAll('de');

            expect(store.tiles).toEqual(tilesData);
            expect(store.error).toBeNull();
            expect(store.loading).toBe(false);
            expect(globalThis.fetch).toHaveBeenCalledWith(
                expect.stringContaining('/api/tiles?locale=de'),
            );
        });

        it('uses store locale when no locale argument is provided', async () => {
            globalThis.fetch = mockFetch({ data: [] });

            const store = useTilesStore();
            store.setLocale('en');
            await store.fetchAll();

            expect(globalThis.fetch).toHaveBeenCalledWith(
                expect.stringContaining('/api/tiles?locale=en'),
            );
        });

        it('defaults to de locale when store locale is not set', async () => {
            globalThis.fetch = mockFetch({ data: [] });

            const store = useTilesStore();
            await store.fetchAll();

            expect(globalThis.fetch).toHaveBeenCalledWith(
                expect.stringContaining('/api/tiles?locale=de'),
            );
        });

        it('sets error on fetch failure', async () => {
            vi.spyOn(console, 'error').mockImplementation(() => {});
            globalThis.fetch = mockFetchError('Network error');

            const store = useTilesStore();
            await store.fetchAll();

            expect(store.error).toBeInstanceOf(Error);
            expect(store.error.message).toBe('Network error');
            expect(store.tiles).toEqual([]);
            expect(store.loading).toBe(false);
        });

        it('toggles loading state during fetch', async () => {
            let resolvePromise;
            globalThis.fetch = vi.fn().mockReturnValue(
                new Promise((resolve) => {
                    resolvePromise = resolve;
                }),
            );

            const store = useTilesStore();
            const fetchPromise = store.fetchAll();

            expect(store.loading).toBe(true);

            resolvePromise({
                ok: true,
                status: 200,
                json: () => Promise.resolve({ data: [] }),
            });
            await fetchPromise;

            expect(store.loading).toBe(false);
        });
    });

    describe('fetchBySlug', () => {
        it('fetches a tile by slug and sets currentTile', async () => {
            const tileData = { id: 1, title: 'Tile 1', slug: 'tile-1' };
            globalThis.fetch = mockFetch({ data: tileData });

            const store = useTilesStore();
            const result = await store.fetchBySlug('tile-1', 'de');

            expect(result).toEqual(tileData);
            expect(store.currentTile).toEqual(tileData);
            expect(store.error).toBeNull();
            expect(globalThis.fetch).toHaveBeenCalledWith(
                expect.stringContaining('/api/tiles/tile-1?locale=de'),
                expect.objectContaining({ cache: 'no-store' }),
            );
        });

        it('returns cached tile on second call without force', async () => {
            const tileData = { id: 1, title: 'Tile 1', slug: 'tile-1' };
            globalThis.fetch = mockFetch({ data: tileData });

            const store = useTilesStore();
            await store.fetchBySlug('tile-1', 'de');
            const result = await store.fetchBySlug('tile-1', 'de');

            expect(result).toEqual(tileData);
            expect(globalThis.fetch).toHaveBeenCalledTimes(1);
        });

        it('bypasses cache when force is true', async () => {
            const tileData = { id: 1, title: 'Tile 1', slug: 'tile-1' };
            globalThis.fetch = mockFetch({ data: tileData });

            const store = useTilesStore();
            await store.fetchBySlug('tile-1', 'de');
            await store.fetchBySlug('tile-1', 'de', true);

            expect(globalThis.fetch).toHaveBeenCalledTimes(2);
        });

        it('sets error with status 404 on not found', async () => {
            globalThis.fetch = mockFetch(null, { ok: false, status: 404 });

            const store = useTilesStore();
            const result = await store.fetchBySlug('nonexistent', 'de');

            expect(result).toBeNull();
            expect(store.error).toEqual({ message: 'Tile not found', status: 404 });
            expect(store.loading).toBe(false);
        });

        it('sets error on non-404 HTTP errors', async () => {
            vi.spyOn(console, 'error').mockImplementation(() => {});
            globalThis.fetch = mockFetch(null, { ok: false, status: 500 });

            const store = useTilesStore();
            const result = await store.fetchBySlug('tile-1', 'de');

            expect(result).toBeNull();
            expect(store.error).toBeInstanceOf(Error);
            expect(store.error.message).toContain('500');
        });

        it('sets error on network failure', async () => {
            vi.spyOn(console, 'error').mockImplementation(() => {});
            globalThis.fetch = mockFetchError('Connection refused');

            const store = useTilesStore();
            const result = await store.fetchBySlug('tile-1', 'de');

            expect(result).toBeNull();
            expect(store.error).toBeInstanceOf(Error);
            expect(store.error.message).toBe('Connection refused');
        });

        it('returns error when no slug is provided', async () => {
            const store = useTilesStore();
            const result = await store.fetchBySlug(null, 'de');

            expect(result).toBeNull();
            expect(store.error).toEqual({ message: 'No slug provided' });
        });

        it('returns error when slug is empty string', async () => {
            const store = useTilesStore();
            const result = await store.fetchBySlug('', 'de');

            expect(result).toBeNull();
            expect(store.error).toEqual({ message: 'No slug provided' });
        });

        it('toggles loading state during fetch', async () => {
            let resolvePromise;
            globalThis.fetch = vi.fn().mockReturnValue(
                new Promise((resolve) => {
                    resolvePromise = resolve;
                }),
            );

            const store = useTilesStore();
            const fetchPromise = store.fetchBySlug('tile-1', 'de');

            expect(store.loading).toBe(true);

            resolvePromise({
                ok: true,
                status: 200,
                json: () => Promise.resolve({ data: { id: 1 } }),
            });
            await fetchPromise;

            expect(store.loading).toBe(false);
        });

        it('caches per locale and slug combination', async () => {
            const tileDataDe = { id: 1, title: 'Kachel 1', slug: 'tile-1' };
            const tileDataEn = { id: 1, title: 'Tile 1', slug: 'tile-1' };
            let callCount = 0;
            globalThis.fetch = vi.fn().mockImplementation(() => {
                callCount++;
                const data = callCount === 1 ? tileDataDe : tileDataEn;
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    statusText: 'OK',
                    json: () => Promise.resolve({ data }),
                    headers: new Headers(),
                });
            });

            const store = useTilesStore();
            await store.fetchBySlug('tile-1', 'de');
            await store.fetchBySlug('tile-1', 'en');

            // Both calls should have been made (different locales = different cache keys)
            expect(globalThis.fetch).toHaveBeenCalledTimes(2);
        });

        it('encodes slug in URL', async () => {
            const tileData = { id: 1, title: 'Special Tile', slug: 'special tile' };
            globalThis.fetch = mockFetch({ data: tileData });

            const store = useTilesStore();
            await store.fetchBySlug('special tile', 'de');

            expect(globalThis.fetch).toHaveBeenCalledWith(
                expect.stringContaining('/api/tiles/special%20tile?locale=de'),
                expect.any(Object),
            );
        });

        it('uses json.data when available, falls back to json', async () => {
            // Test the fallback: json without .data wrapper
            const tileData = { id: 1, title: 'Tile 1', slug: 'tile-1' };
            globalThis.fetch = vi.fn().mockResolvedValue({
                ok: true,
                status: 200,
                statusText: 'OK',
                json: () => Promise.resolve(tileData), // No .data wrapper
                headers: new Headers(),
            });

            const store = useTilesStore();
            const result = await store.fetchBySlug('tile-1', 'de');

            expect(result).toEqual(tileData);
        });
    });
});
