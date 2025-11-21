import { defineStore } from 'pinia';

export const useBrandingStore = defineStore('branding', {
    state: () => ({
        primaryColor: '#1976d2',
        secondaryColor: '#0d47a1',
        logoUrl: null,
    }),
    actions: {
        async fetch() {
            try {
                const apiUrl = window.APP_URL || '';
                const res = await fetch(
                    apiUrl + '/api/config/branding',
                    { credentials: 'include' },
                );
                const json = await res.json();

                const { primary_color, secondary_color, logo_url } = json.data;

                this.primaryColor = primary_color;
                this.secondaryColor = secondary_color;
                this.logoUrl = logo_url;

                document.documentElement.style.setProperty('--primary-color', this.primaryColor);
                document.documentElement.style.setProperty('--secondary-color', this.secondaryColor);
            } catch (error) {
                logError('Failed to fetch branding settings:', error);
            }
        },
    },
});

