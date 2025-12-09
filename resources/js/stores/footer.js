import { defineStore } from 'pinia';
import { getApiBaseUrl } from '../utils/api';

export const useFooterStore = defineStore('footer', {
    state: () => ({
        footerNavigationItems: [],
        socialLinks: [],
        layoutType: 'columns', // 'columns' or 'simple'
        columns: 4,
        socialLinksEnabled: false,
        copyrightText: null,
        loading: false,
        error: null,
    }),
    actions: {
        /**
         * Fetch footer configuration from API
         * @param {string} locale - Locale ('de' or 'en')
         * @returns {Promise<Object|null>} Footer configuration data, or null on failure
         */
        async fetchConfig(locale = 'de') {
            this.loading = true;
            this.error = null;
            
            try {
                const apiUrl = getApiBaseUrl();
                const res = await fetch(
                    `${apiUrl}/api/config/footer?locale=${locale}`,
                    { credentials: 'include' }
                );
                
                if (!res.ok) {
                    throw new Error(`Failed to fetch footer config: ${res.status} ${res.statusText}`);
                }
                
                const json = await res.json();
                const data = json.data || json;
                
                this.footerNavigationItems = data.footer_navigation_items || [];
                this.socialLinks = data.social_links || [];
                this.layoutType = data.layout_type || 'columns';
                this.columns = data.columns || 4;
                this.socialLinksEnabled = data.social_links_enabled || false;
                this.copyrightText = data.copyright_text || null;
                
                return data;
            } catch (err) {
                this.error = err;
                logError('Failed to fetch footer config:', err);
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
         * Get footer navigation items
         */
        getFooterNavigationItems: (state) => state.footerNavigationItems,
        
        /**
         * Get social links
         */
        getSocialLinks: (state) => state.socialLinks,
    },
});
