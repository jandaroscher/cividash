import { defineStore } from 'pinia';
import { getApiBaseUrl } from '../utils/api';
import { logError } from '../lib/log.js';

export const usePagesStore = defineStore('pages', {
    state: () => ({
        currentPage: null,
        pages: [],
        loading: false,
        error: null,
        // Cache for pages by locale:slug key
        cache: new Map(),
    }),
    actions: {
        /**
         * Fetch a page by ID
         * @param {number|string} id - Page ID
         * @param {string} locale - Locale ('de' or 'en')
         * @param {boolean} force - Bypass local cache
         * @returns {Promise<Object>} Page data
         */
        async fetchPage(id, locale = 'de', force = false) {
            const cacheKey = `${locale}:${id}`;
            if (!force && this.cache.has(cacheKey)) {
                const cachedPage = this.cache.get(cacheKey);
                this.currentPage = cachedPage;
                return cachedPage;
            }

            this.loading = true;
            this.error = null;
            
            try {
                const apiUrl = getApiBaseUrl();
                const res = await fetch(
                    `${apiUrl}/api/content/pages/${id}?locale=${locale}`,
                    { credentials: 'include', cache: 'no-store' }
                );
                
                if (!res.ok) {
                    if (res.status === 404) {
                        this.error = { message: 'Page not found', status: 404 };
                        return null;
                    }
                    throw new Error(`Failed to fetch page: ${res.status} ${res.statusText}`);
                }
                
                const json = await res.json();
                const pageData = json.data || json;
                
                // Cache the page
                this.cache.set(cacheKey, pageData);
                
                this.currentPage = pageData;
                return pageData;
            } catch (err) {
                this.error = err;
                logError('Failed to fetch page:', err);
                return null;
            } finally {
                this.loading = false;
            }
        },
        
        /**
         * Fetch the root/home page
         * @param {string} locale - Locale ('de' or 'en')
         * @param {boolean} force - Bypass local cache
         * @returns {Promise<Object>} Root page data
         */
        async fetchRootPage(locale = 'de', force = false) {
            const cacheKey = `${locale}:root`;
            if (!force && this.cache.has(cacheKey)) {
                const cachedPage = this.cache.get(cacheKey);
                this.currentPage = cachedPage;
                return cachedPage;
            }

            this.loading = true;
            this.error = null;
            
            try {
                const apiUrl = getApiBaseUrl();
                const res = await fetch(
                    `${apiUrl}/api/content/pages/root?locale=${locale}`,
                    { credentials: 'include', cache: 'no-store' }
                );
                
                if (!res.ok) {
                    if (res.status === 404) {
                        this.error = { message: 'Root page not found', status: 404 };
                        return null;
                    }
                    throw new Error(`Failed to fetch root page: ${res.status} ${res.statusText}`);
                }
                
                const json = await res.json();
                const pageData = json.data || json;
                
                // Cache the page
                this.cache.set(cacheKey, pageData);
                
                this.currentPage = pageData;
                return pageData;
            } catch (err) {
                this.error = err;
                logError('Failed to fetch root page:', err);
                return null;
            } finally {
                this.loading = false;
            }
        },
        
        /**
         * Fetch list of all pages (metadata only)
         * @param {string} locale - Locale ('de' or 'en')
         * @returns {Promise<Array>} Array of page metadata
         */
        async fetchPageList(locale = 'de') {
            // Check cache first
            const cacheKey = `list:${locale}`;
            if (this.cache.has(cacheKey)) {
                return this.cache.get(cacheKey);
            }
            
            this.loading = true;
            this.error = null;
            
            try {
                const apiUrl = getApiBaseUrl();
                const res = await fetch(
                    `${apiUrl}/api/content/pages?locale=${locale}`,
                    { credentials: 'include', cache: 'no-store' }
                );
                
                if (!res.ok) {
                    throw new Error(`Failed to fetch page list: ${res.status} ${res.statusText}`);
                }
                
                const json = await res.json();
                const pages = json.data || [];
                
                this.pages = pages;
                // Cache the page list
                this.cache.set(cacheKey, pages);
                return pages;
            } catch (err) {
                this.error = err;
                logError('Failed to fetch page list:', err);
                return [];
            } finally {
                this.loading = false;
            }
        },
        
        /**
         * Resolve a page by walking a hierarchical slug path.
         * E.g. "kontakt/testseite" → find root page with slug "kontakt",
         * then child with slug "testseite" and parent_id matching.
         * @param {string} slug - Page slug (may contain "/" for nested pages)
         * @param {string} locale - Locale ('de' or 'en')
         * @param {Array} pages - Page list from API
         * @returns {Object|null} Matched page metadata or null
         */
        resolvePageByPath(slug, locale, pages) {
            const segments = slug.split('/');
            const getSlug = (page) =>
                typeof page.slug === 'string' ? page.slug : page.slug?.[locale];

            // Collect IDs of root pages (slug "/" or similar) — their children
            // appear as top-level URL segments despite having a non-null parent_id.
            const rootPageIds = pages
                .filter((p) => getSlug(p) === '/' || getSlug(p) === '')
                .map((p) => p.id);

            let parentId = null;
            let matched = null;

            for (let i = 0; i < segments.length; i++) {
                const segment = segments[i];
                matched = pages.find((page) => {
                    if (getSlug(page) !== segment) return false;
                    if (i === 0) {
                        // First segment: accept parent_id null OR root page children
                        return page.parent_id === null || rootPageIds.includes(page.parent_id);
                    }
                    return page.parent_id === parentId;
                });
                if (!matched) return null;
                parentId = matched.id;
            }

            return matched;
        },

        /**
         * Fetch a page by slug (supports hierarchical paths like "kontakt/testseite")
         * First fetches the page list, resolves the page by walking the path, then fetches full page data
         * @param {string} slug - Page slug (may contain "/" for nested pages)
         * @param {string} locale - Locale ('de' or 'en')
         * @param {boolean} force - Bypass local cache
         * @returns {Promise<Object>} Page data
         */
        async fetchPageBySlug(slug, locale = 'de', force = false) {
            // Check cache first
            const cacheKey = `${locale}:${slug}`;
            if (!force && this.cache.has(cacheKey)) {
                const cachedPage = this.cache.get(cacheKey);
                this.currentPage = cachedPage;
                return cachedPage;
            }

            this.loading = true;
            this.error = null;

            try {
                // Inline fetch to avoid loading state conflicts
                const apiUrl = getApiBaseUrl();
                const res = await fetch(
                    `${apiUrl}/api/content/pages?locale=${locale}`,
                    { credentials: 'include', cache: 'no-store' }
                );

                if (!res.ok) {
                    throw new Error(`Failed to fetch page list: ${res.status} ${res.statusText}`);
                }

                const json = await res.json();
                const pages = json.data || [];

                // Resolve page by walking hierarchical path
                const pageMeta = this.resolvePageByPath(slug, locale, pages);

                if (!pageMeta || !pageMeta.id) {
                    this.error = { message: 'Page not found', status: 404 };
                    return null;
                }

                // Inline fetch full page data by ID to avoid loading state conflicts
                const pageRes = await fetch(
                    `${apiUrl}/api/content/pages/${pageMeta.id}?locale=${locale}`,
                    { credentials: 'include', cache: 'no-store' }
                );

                if (!pageRes.ok) {
                    if (pageRes.status === 404) {
                        this.error = { message: 'Page not found', status: 404 };
                        return null;
                    }
                    throw new Error(`Failed to fetch page: ${pageRes.status} ${pageRes.statusText}`);
                }

                const pageJson = await pageRes.json();
                const pageData = pageJson.data || pageJson;

                // Cache the page by both ID and slug
                const idCacheKey = `${locale}:${pageData.id}`;
                this.cache.set(idCacheKey, pageData);
                this.cache.set(cacheKey, pageData);

                this.currentPage = pageData;
                return pageData;
            } catch (err) {
                this.error = err;
                logError('Failed to fetch page by slug:', err);
                return null;
            } finally {
                this.loading = false;
            }
        },
        
        /**
         * Clear the cache
         */
        clearCache() {
            this.cache.clear();
            this.currentPage = null;
        },
    },
    getters: {
        /**
         * Get current page
         */
        getCurrentPage: (state) => state.currentPage,
        
        /**
         * Check if currently loading
         */
        isLoading: (state) => state.loading,
        
        /**
         * Check if current page is not found (404)
         */
        isNotFound: (state) => state.error?.status === 404,
    },
});

