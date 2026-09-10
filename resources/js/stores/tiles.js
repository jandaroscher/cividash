import { defineStore } from 'pinia';
import { getApiBaseUrl } from '../utils/api';
import { logError } from '../lib/log.js';

export const useTilesStore = defineStore('tiles', {
    state: () => ({
        tiles: [],
        currentTile: null,
        loading: false,
        error: null,
        locale: 'de', // Default locale
        cache: new Map(),
    }),
    actions: {
        setLocale(locale) {
            // Validate locale
            if (locale && ['de', 'en'].includes(locale)) {
                this.locale = locale;
            }
        },
        async fetchAll(locale = null) {
            this.loading = true;
            this.error = null;
            try {
                const apiUrl = getApiBaseUrl();
                // Use provided locale, store locale, or default to 'de'
                const requestLocale = locale || this.locale || 'de';
                const res = await fetch(`${apiUrl}/api/tiles?locale=${requestLocale}`);
                const json = await res.json();
                // ResourceCollection comes back as { data: [ … ] }
                this.tiles = json.data;
            } catch (err) {
                this.error = err;
            } finally {
                this.loading = false;
            }
        },
        async fetchBySlug(slug, locale = 'de', force = false) {
            if (!slug) {
                this.error = { message: 'No slug provided' };
                return null;
            }

            const cacheKey = `${locale}:${slug}`;
            if (!force && this.cache.has(cacheKey)) {
                const cachedTile = this.cache.get(cacheKey);
                this.currentTile = cachedTile;
                return cachedTile;
            }

            this.loading = true;
            this.error = null;

            try {
                const apiUrl = getApiBaseUrl();
                const res = await fetch(
                    `${apiUrl}/api/tiles/${encodeURIComponent(slug)}?locale=${locale}`,
                    { cache: 'no-store' }
                );

                if (!res.ok) {
                    if (res.status === 404) {
                        this.error = { message: 'Tile not found', status: 404 };
                        return null;
                    }
                    throw new Error(`Failed to fetch tile: ${res.status} ${res.statusText}`);
                }

                const json = await res.json();
                const tileData = json.data || json;
                this.cache.set(cacheKey, tileData);
                this.currentTile = tileData;
                return tileData;
            } catch (err) {
                this.error = err;
                logError('Failed to fetch tile:', err);
                return null;
            } finally {
                this.loading = false;
            }
        },
    },
});

