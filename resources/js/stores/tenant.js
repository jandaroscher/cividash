import { defineStore } from 'pinia';

export const useTenantStore = defineStore('tenant', {
    state: () => ({
        slug: window.__TENANT__?.slug ?? 'default',
        name: window.__TENANT__?.name ?? null,
        themeSlug: window.__TENANT__?.theme_slug ?? null,
    }),
});
