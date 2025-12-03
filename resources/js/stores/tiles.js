import { defineStore } from 'pinia';

export const useTilesStore = defineStore('tiles', {
    state: () => ({
        tiles: [],
        loading: false,
        error: null,
        locale: 'de', // Default locale
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
                const apiUrl = window.APP_URL || '';
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
    },
});

