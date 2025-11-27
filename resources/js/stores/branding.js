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

                document.documentElement.style.setProperty('--primary-color', this.primaryColor || '#1976d2');
                document.documentElement.style.setProperty('--secondary-color', this.secondaryColor || '#0d47a1');
                
                // Set accent color for themeable styling (use primary color as accent, or fallback to default)
                document.documentElement.style.setProperty('--accent-color', this.primaryColor || '#E30613');
                document.documentElement.style.setProperty('--accent-color-dark', this.secondaryColor || '#891F00');
            } catch (error) {
                logError('Failed to fetch branding settings:', error);
            }
        },
    },
});

