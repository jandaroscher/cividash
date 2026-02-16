import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { usePagesStore } from '@/stores/pages';
import { mockFetch, mockFetchError } from '../setup';

describe('pagesStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.restoreAllMocks();
    });

    describe('initial state', () => {
        it('has correct default values', () => {
            const store = usePagesStore();
            expect(store.currentPage).toBeNull();
            expect(store.pages).toEqual([]);
            expect(store.loading).toBe(false);
            expect(store.error).toBeNull();
            expect(store.cache).toBeInstanceOf(Map);
            expect(store.cache.size).toBe(0);
        });
    });

    describe('getters', () => {
        it('getCurrentPage returns currentPage', () => {
            const store = usePagesStore();
            store.currentPage = { id: 1, title: 'Test' };
            expect(store.getCurrentPage).toEqual({ id: 1, title: 'Test' });
        });

        it('isLoading returns loading state', () => {
            const store = usePagesStore();
            expect(store.isLoading).toBe(false);
            store.loading = true;
            expect(store.isLoading).toBe(true);
        });

        it('isNotFound returns true when error has status 404', () => {
            const store = usePagesStore();
            store.error = { message: 'Page not found', status: 404 };
            expect(store.isNotFound).toBe(true);
        });

        it('isNotFound returns false when no error', () => {
            const store = usePagesStore();
            expect(store.isNotFound).toBe(false);
        });

        it('isNotFound returns false for non-404 errors', () => {
            const store = usePagesStore();
            store.error = { message: 'Server error', status: 500 };
            expect(store.isNotFound).toBe(false);
        });
    });

    describe('fetchPage', () => {
        it('fetches a page by ID and sets currentPage', async () => {
            const pageData = { id: 1, title: 'Home', slug: 'home', blocks: [] };
            globalThis.fetch = mockFetch({ data: pageData });

            const store = usePagesStore();
            const result = await store.fetchPage(1, 'de');

            expect(result).toEqual(pageData);
            expect(store.currentPage).toEqual(pageData);
            expect(store.error).toBeNull();
            expect(globalThis.fetch).toHaveBeenCalledWith(
                expect.stringContaining('/api/content/pages/1?locale=de'),
                expect.objectContaining({ credentials: 'include', cache: 'no-store' }),
            );
        });

        it('uses cache on second call', async () => {
            const pageData = { id: 1, title: 'Home' };
            globalThis.fetch = mockFetch({ data: pageData });

            const store = usePagesStore();
            await store.fetchPage(1, 'de');
            const result = await store.fetchPage(1, 'de');

            expect(result).toEqual(pageData);
            expect(globalThis.fetch).toHaveBeenCalledTimes(1);
        });

        it('bypasses cache when force is true', async () => {
            const pageData = { id: 1, title: 'Home' };
            globalThis.fetch = mockFetch({ data: pageData });

            const store = usePagesStore();
            await store.fetchPage(1, 'de');
            await store.fetchPage(1, 'de', true);

            expect(globalThis.fetch).toHaveBeenCalledTimes(2);
        });

        it('sets error with status 404 on not found', async () => {
            globalThis.fetch = mockFetch(null, { ok: false, status: 404 });

            const store = usePagesStore();
            const result = await store.fetchPage(999, 'de');

            expect(result).toBeNull();
            expect(store.error).toEqual({ message: 'Page not found', status: 404 });
        });

        it('sets error on non-404 HTTP errors', async () => {
            vi.spyOn(console, 'error').mockImplementation(() => {});
            globalThis.fetch = mockFetch(null, { ok: false, status: 500 });

            const store = usePagesStore();
            const result = await store.fetchPage(1, 'de');

            expect(result).toBeNull();
            expect(store.error).toBeInstanceOf(Error);
            expect(store.error.message).toContain('500');
        });

        it('sets error on network failure', async () => {
            vi.spyOn(console, 'error').mockImplementation(() => {});
            globalThis.fetch = mockFetchError('Network error');

            const store = usePagesStore();
            const result = await store.fetchPage(1, 'de');

            expect(result).toBeNull();
            expect(store.error).toBeInstanceOf(Error);
            expect(store.error.message).toBe('Network error');
        });

        it('toggles loading state during fetch', async () => {
            let resolvePromise;
            globalThis.fetch = vi.fn().mockReturnValue(
                new Promise((resolve) => {
                    resolvePromise = resolve;
                }),
            );

            const store = usePagesStore();
            const fetchPromise = store.fetchPage(1, 'de');

            expect(store.loading).toBe(true);

            resolvePromise({
                ok: true,
                status: 200,
                json: () => Promise.resolve({ data: { id: 1 } }),
            });
            await fetchPromise;

            expect(store.loading).toBe(false);
        });

        it('defaults locale to de', async () => {
            globalThis.fetch = mockFetch({ data: { id: 1 } });

            const store = usePagesStore();
            await store.fetchPage(1);

            expect(globalThis.fetch).toHaveBeenCalledWith(
                expect.stringContaining('locale=de'),
                expect.any(Object),
            );
        });

        it('falls back to json when json.data is missing', async () => {
            const pageData = { id: 1, title: 'Home' };
            globalThis.fetch = vi.fn().mockResolvedValue({
                ok: true,
                status: 200,
                statusText: 'OK',
                json: () => Promise.resolve(pageData),
                headers: new Headers(),
            });

            const store = usePagesStore();
            const result = await store.fetchPage(1, 'de');

            expect(result).toEqual(pageData);
        });
    });

    describe('fetchRootPage', () => {
        it('fetches root page from correct endpoint', async () => {
            const rootPageData = { id: 1, title: 'Home', slug: '/', blocks: [] };
            globalThis.fetch = mockFetch({ data: rootPageData });

            const store = usePagesStore();
            const result = await store.fetchRootPage('de');

            expect(result).toEqual(rootPageData);
            expect(store.currentPage).toEqual(rootPageData);
            expect(globalThis.fetch).toHaveBeenCalledWith(
                expect.stringContaining('/api/content/pages/root?locale=de'),
                expect.any(Object),
            );
        });

        it('uses cache on second call', async () => {
            const rootPageData = { id: 1, title: 'Home' };
            globalThis.fetch = mockFetch({ data: rootPageData });

            const store = usePagesStore();
            await store.fetchRootPage('de');
            const result = await store.fetchRootPage('de');

            expect(result).toEqual(rootPageData);
            expect(globalThis.fetch).toHaveBeenCalledTimes(1);
        });

        it('bypasses cache when force is true', async () => {
            globalThis.fetch = mockFetch({ data: { id: 1 } });

            const store = usePagesStore();
            await store.fetchRootPage('de');
            await store.fetchRootPage('de', true);

            expect(globalThis.fetch).toHaveBeenCalledTimes(2);
        });

        it('sets 404 error when root page not found', async () => {
            globalThis.fetch = mockFetch(null, { ok: false, status: 404 });

            const store = usePagesStore();
            const result = await store.fetchRootPage('de');

            expect(result).toBeNull();
            expect(store.error).toEqual({ message: 'Root page not found', status: 404 });
        });

        it('handles network errors', async () => {
            vi.spyOn(console, 'error').mockImplementation(() => {});
            globalThis.fetch = mockFetchError('Connection refused');

            const store = usePagesStore();
            const result = await store.fetchRootPage('de');

            expect(result).toBeNull();
            expect(store.error).toBeInstanceOf(Error);
        });
    });

    describe('fetchPageList', () => {
        it('fetches page list and sets pages array', async () => {
            const pagesData = [
                { id: 1, title: 'Home', slug: 'home' },
                { id: 2, title: 'About', slug: 'about' },
            ];
            globalThis.fetch = mockFetch({ data: pagesData });

            const store = usePagesStore();
            const result = await store.fetchPageList('de');

            expect(result).toEqual(pagesData);
            expect(store.pages).toEqual(pagesData);
            expect(globalThis.fetch).toHaveBeenCalledWith(
                expect.stringContaining('/api/content/pages?locale=de'),
                expect.any(Object),
            );
        });

        it('uses cache on second call', async () => {
            const pagesData = [{ id: 1, title: 'Home' }];
            globalThis.fetch = mockFetch({ data: pagesData });

            const store = usePagesStore();
            await store.fetchPageList('de');
            const result = await store.fetchPageList('de');

            expect(result).toEqual(pagesData);
            expect(globalThis.fetch).toHaveBeenCalledTimes(1);
        });

        it('handles error and returns empty array', async () => {
            vi.spyOn(console, 'error').mockImplementation(() => {});
            globalThis.fetch = mockFetchError('Network error');

            const store = usePagesStore();
            const result = await store.fetchPageList('de');

            expect(result).toEqual([]);
            expect(store.error).toBeInstanceOf(Error);
        });

        it('handles non-ok response', async () => {
            vi.spyOn(console, 'error').mockImplementation(() => {});
            globalThis.fetch = mockFetch(null, { ok: false, status: 500 });

            const store = usePagesStore();
            const result = await store.fetchPageList('de');

            expect(result).toEqual([]);
            expect(store.error).toBeInstanceOf(Error);
        });

        it('returns empty array when json.data is missing', async () => {
            globalThis.fetch = vi.fn().mockResolvedValue({
                ok: true,
                status: 200,
                statusText: 'OK',
                json: () => Promise.resolve({}),
                headers: new Headers(),
            });

            const store = usePagesStore();
            const result = await store.fetchPageList('de');

            expect(result).toEqual([]);
            expect(store.pages).toEqual([]);
        });
    });

    describe('fetchPageBySlug', () => {
        it('fetches page list then fetches page by ID', async () => {
            const pagesListData = [
                { id: 1, title: 'Home', slug: 'home' },
                { id: 2, title: 'About', slug: 'about' },
            ];
            const fullPageData = { id: 2, title: 'About', slug: 'about', blocks: [{ type: 'text' }] };

            let callCount = 0;
            globalThis.fetch = vi.fn().mockImplementation((url) => {
                callCount++;
                if (url.includes('/api/content/pages?')) {
                    return Promise.resolve({
                        ok: true,
                        status: 200,
                        statusText: 'OK',
                        json: () => Promise.resolve({ data: pagesListData }),
                        headers: new Headers(),
                    });
                }
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    statusText: 'OK',
                    json: () => Promise.resolve({ data: fullPageData }),
                    headers: new Headers(),
                });
            });

            const store = usePagesStore();
            const result = await store.fetchPageBySlug('about', 'de');

            expect(result).toEqual(fullPageData);
            expect(store.currentPage).toEqual(fullPageData);
            expect(globalThis.fetch).toHaveBeenCalledTimes(2);
        });

        it('uses cache on second call', async () => {
            const pagesListData = [{ id: 1, title: 'Home', slug: 'home' }];
            const fullPageData = { id: 1, title: 'Home', slug: 'home', blocks: [] };

            globalThis.fetch = vi.fn().mockImplementation((url) => {
                if (url.includes('/api/content/pages?')) {
                    return Promise.resolve({
                        ok: true,
                        status: 200,
                        statusText: 'OK',
                        json: () => Promise.resolve({ data: pagesListData }),
                        headers: new Headers(),
                    });
                }
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    statusText: 'OK',
                    json: () => Promise.resolve({ data: fullPageData }),
                    headers: new Headers(),
                });
            });

            const store = usePagesStore();
            await store.fetchPageBySlug('home', 'de');
            const result = await store.fetchPageBySlug('home', 'de');

            // Second call should use cache, so only 2 fetch calls total (from first call)
            expect(result).toEqual(fullPageData);
            expect(globalThis.fetch).toHaveBeenCalledTimes(2);
        });

        it('sets 404 when slug not found in page list', async () => {
            const pagesListData = [{ id: 1, title: 'Home', slug: 'home' }];
            globalThis.fetch = vi.fn().mockResolvedValue({
                ok: true,
                status: 200,
                statusText: 'OK',
                json: () => Promise.resolve({ data: pagesListData }),
                headers: new Headers(),
            });

            const store = usePagesStore();
            const result = await store.fetchPageBySlug('nonexistent', 'de');

            expect(result).toBeNull();
            expect(store.error).toEqual({ message: 'Page not found', status: 404 });
        });

        it('handles localized slug objects', async () => {
            const pagesListData = [
                { id: 1, title: 'Home', slug: { de: 'startseite', en: 'home' } },
            ];
            const fullPageData = { id: 1, title: 'Home', slug: { de: 'startseite', en: 'home' }, blocks: [] };

            globalThis.fetch = vi.fn().mockImplementation((url) => {
                if (url.includes('/api/content/pages?')) {
                    return Promise.resolve({
                        ok: true,
                        status: 200,
                        statusText: 'OK',
                        json: () => Promise.resolve({ data: pagesListData }),
                        headers: new Headers(),
                    });
                }
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    statusText: 'OK',
                    json: () => Promise.resolve({ data: fullPageData }),
                    headers: new Headers(),
                });
            });

            const store = usePagesStore();
            const result = await store.fetchPageBySlug('startseite', 'de');

            expect(result).toEqual(fullPageData);
        });

        it('handles 404 on the page detail fetch', async () => {
            const pagesListData = [{ id: 1, title: 'Home', slug: 'home' }];
            globalThis.fetch = vi.fn().mockImplementation((url) => {
                if (url.includes('/api/content/pages?')) {
                    return Promise.resolve({
                        ok: true,
                        status: 200,
                        statusText: 'OK',
                        json: () => Promise.resolve({ data: pagesListData }),
                        headers: new Headers(),
                    });
                }
                return Promise.resolve({
                    ok: false,
                    status: 404,
                    statusText: 'Not Found',
                    json: () => Promise.resolve({}),
                    headers: new Headers(),
                });
            });

            const store = usePagesStore();
            const result = await store.fetchPageBySlug('home', 'de');

            expect(result).toBeNull();
            expect(store.error).toEqual({ message: 'Page not found', status: 404 });
        });

        it('handles network errors', async () => {
            vi.spyOn(console, 'error').mockImplementation(() => {});
            globalThis.fetch = mockFetchError('Connection refused');

            const store = usePagesStore();
            const result = await store.fetchPageBySlug('home', 'de');

            expect(result).toBeNull();
            expect(store.error).toBeInstanceOf(Error);
        });
    });

    describe('clearCache', () => {
        it('resets cache and currentPage', async () => {
            const pageData = { id: 1, title: 'Home' };
            globalThis.fetch = mockFetch({ data: pageData });

            const store = usePagesStore();
            await store.fetchPage(1, 'de');

            expect(store.currentPage).toEqual(pageData);
            expect(store.cache.size).toBeGreaterThan(0);

            store.clearCache();

            expect(store.currentPage).toBeNull();
            expect(store.cache.size).toBe(0);
        });
    });
});
