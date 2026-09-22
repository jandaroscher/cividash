import { defineStore } from 'pinia';
import { getApiBaseUrl } from '../utils/api';
import { logError } from '../lib/log.js';

export const useHeaderStore = defineStore('header', {
    state: () => ({
        navigationItems: [],
        dropdownEnabled: false,
        englishTranslationActive: true,
        siteName: '',
        loading: false,
        error: null,
    }),
    actions: {
        /**
         * Fetch header configuration from API
         * @param {string} locale - Locale ('de' or 'en')
         * @returns {Promise<Object|null>} Header configuration data, or null on failure
         */
        async fetchConfig(locale = 'de') {
            this.loading = true;
            this.error = null;
            
            try {
                const apiUrl = getApiBaseUrl();
                const res = await fetch(
                    `${apiUrl}/api/config/header?locale=${locale}`,
                    { credentials: 'include' }
                );
                
                if (!res.ok) {
                    throw new Error(`Failed to fetch header config: ${res.status} ${res.statusText}`);
                }
                
                const json = await res.json();
                const data = json.data || json;
                
                this.navigationItems = data.navigation_items || [];
                this.dropdownEnabled = data.dropdown_enabled || false;
                this.englishTranslationActive = data.english_translation_active === true;
                this.siteName = data.site_name || this.siteName;
                
                return data;
            } catch (err) {
                // Store UI-friendly error message
                this.error = err.message || 'Failed to fetch header configuration';
                logError('Failed to fetch header config:', err);
                return null;
            } finally {
                this.loading = false;
            }
        },
    },
    getters: {
        /**
         * Check if currently loading
         */
        isLoading: (state) => state.loading,
        
        /**
         * Get navigation items
         */
        getNavigationItems: (state) => state.navigationItems,
    },
});
