import { defineStore } from 'pinia';

export const useBrandingStore = defineStore('branding', {
    state: () => ({
        primaryColor: '#1976d2',
        secondaryColor: '#0d47a1',
        logoUrl: null,
        categoryColors: {
            gerecht: 'bg-orange-100',
            just: 'bg-orange-100',
            produktiv: 'bg-blue-100',
            productive: 'bg-blue-100',
            gruen: 'bg-green-100',
            grün: 'bg-green-100', // Support umlaut version
            green: 'bg-green-100',
        },
        sliderColors: {
            rail: '#191919',
            handle: '#E30613',
            handleBorder: '#191919',
        },
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

                const {
                    primary_color,
                    secondary_color,
                    logo_url,
                    category_colors,
                    slider_colors,
                } = json.data;

                this.primaryColor = primary_color || this.primaryColor;
                this.secondaryColor = secondary_color || this.secondaryColor;
                this.logoUrl = logo_url;

                // Update category colors if provided
                if (category_colors) {
                    this.categoryColors = { ...this.categoryColors, ...category_colors };
                }

                // Update slider colors if provided
                if (slider_colors) {
                    this.sliderColors = { ...this.sliderColors, ...slider_colors };
                }

                document.documentElement.style.setProperty('--primary-color', this.primaryColor || '#1976d2');
                document.documentElement.style.setProperty('--secondary-color', this.secondaryColor || '#0d47a1');
                
                // Set accent color for themeable styling (use primary color as accent, or fallback to default)
                document.documentElement.style.setProperty('--accent-color', this.primaryColor || '#E30613');
                document.documentElement.style.setProperty('--accent-color-dark', this.secondaryColor || '#891F00');

                // Set slider colors as CSS variables
                document.documentElement.style.setProperty('--slider-rail-color', this.sliderColors.rail || '#191919');
                document.documentElement.style.setProperty('--slider-handle-color', this.sliderColors.handle || '#E30613');
                document.documentElement.style.setProperty('--slider-handle-border-color', this.sliderColors.handleBorder || '#191919');
            } catch (error) {
                logError('Failed to fetch branding settings:', error);
            }
        },
        getCategoryColor(slug) {
            // Normalize slug to lowercase for case-insensitive matching
            const normalizedSlug = slug?.toLowerCase();
            
            // Direct match
            if (this.categoryColors[normalizedSlug]) {
                return this.categoryColors[normalizedSlug];
            }
            
            // Fallback: try to match common variations
            const fallbackMap = {
                'grün': 'bg-green-100',
                'gruen': 'bg-green-100',
                'green': 'bg-green-100',
                'gerecht': 'bg-orange-100',
                'just': 'bg-orange-100',
                'produktiv': 'bg-blue-100',
                'productive': 'bg-blue-100',
            };
            
            return fallbackMap[normalizedSlug] || 'bg-gray-100';
        },
    },
});

