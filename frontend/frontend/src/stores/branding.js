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
                const res = await fetch(
                    import.meta.env.VITE_API_URL + '/api/config/branding',
                    { credentials: 'include' },
                );
                const json = await res.json();

                const { primary_color, secondary_color, logo_url } = json.data;

                this.primaryColor   = primary_color;
                this.secondaryColor = secondary_color;
                this.logoUrl        = logo_url;
;
                console.log('branding payload:', json.data);
                console.log('primaryColor:', this.primaryColor);
                console.log('secondaryColor:', this.secondaryColor);
                console.log('logoUrl:', this.logoUrl);

                document.documentElement.style.setProperty('--primary-color', this.primaryColor);
                document.documentElement.style.setProperty('--secondary-color', this.secondaryColor);
            } catch (error) {
                logError('Failed to fetch branding settings:', error);
            }
        },
    },
});
