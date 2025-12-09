import { defineStore } from 'pinia';
import { getApiBaseUrl } from '../utils/api';

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
         * @returns {Promise<Object>} Page data
         */
        async fetchPage(id, locale = 'de') {
            this.loading = true;
            this.error = null;
            
            try {
                const apiUrl = getApiBaseUrl();
                const res = await fetch(
                    `${apiUrl}/api/content/pages/${id}?locale=${locale}`,
                    { credentials: 'include' }
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
                const cacheKey = `${locale}:${pageData.id}`;
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
         * @returns {Promise<Object>} Root page data
         */
        async fetchRootPage(locale = 'de') {
            this.loading = true;
            this.error = null;
            
            try {
                const apiUrl = getApiBaseUrl();
                const res = await fetch(
                    `${apiUrl}/api/content/pages/root?locale=${locale}`,
                    { credentials: 'include' }
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
                const cacheKey = `${locale}:root`;
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
                    { credentials: 'include' }
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
         * Fetch a page by slug
         * First fetches the page list, finds the page by slug, then fetches full page data
         * @param {string} slug - Page slug
         * @param {string} locale - Locale ('de' or 'en')
         * @returns {Promise<Object>} Page data
         */
        async fetchPageBySlug(slug, locale = 'de') {
            // Check cache first
            const cacheKey = `${locale}:${slug}`;
            if (this.cache.has(cacheKey)) {
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
                    { credentials: 'include' }
                );
                
                if (!res.ok) {
                    throw new Error(`Failed to fetch page list: ${res.status} ${res.statusText}`);
                }
                
                const json = await res.json();
                const pages = json.data || [];
                
                // Find page by slug
                const pageMeta = pages.find(page => {
                    const pageSlug = typeof page.slug === 'string' ? page.slug : page.slug?.[locale];
                    return pageSlug === slug;
                });
                
                if (!pageMeta || !pageMeta.id) {
                    this.error = { message: 'Page not found', status: 404 };
                    return null;
                }
                
                // Inline fetch full page data by ID to avoid loading state conflicts
                const pageRes = await fetch(
                    `${apiUrl}/api/content/pages/${pageMeta.id}?locale=${locale}`,
                    { credentials: 'include' }
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

