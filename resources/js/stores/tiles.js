import { defineStore } from 'pinia';

export const useTilesStore = defineStore('tiles', {
    state: () => ({
        tiles: [],
        loading: false,
        error: null,
    }),
    actions: {
        async fetchAll() {
            this.loading = true;
            this.error = null;
            try {
                const apiUrl = window.APP_URL || '';
                const res = await fetch(`${apiUrl}/api/tiles`);
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

