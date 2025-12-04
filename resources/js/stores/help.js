import { defineStore } from 'pinia';

/**
 * Deep merge utility function for help content
 * Merges source into target, preserving nested defaults
 * @param {Object} target - Target object (defaults)
 * @param {Object} source - Source object (API data)
 * @returns {Object} Merged object
 */
function deepMerge(target, source) {
    const result = { ...target };
    
    for (const key in source) {
        if (source.hasOwnProperty(key)) {
            if (
                typeof source[key] === 'object' &&
                source[key] !== null &&
                !Array.isArray(source[key]) &&
                typeof target[key] === 'object' &&
                target[key] !== null &&
                !Array.isArray(target[key])
            ) {
                // Recursively merge nested objects
                result[key] = deepMerge(target[key], source[key]);
            } else {
                // Override with source value
                result[key] = source[key];
            }
        }
    }
    
    return result;
}

// Default help content (fallback if API doesn't provide)
const defaultHelpContent = {
    tooltips: {
        filterDimensions: {
            de: 'Filtern Sie Kacheln nach Handlungsdimensionen',
            en: 'Filter tiles by action dimensions',
        },
        filterFields: {
            de: 'Filtern Sie Kacheln nach Handlungsfeldern',
            en: 'Filter tiles by action fields',
        },
        filterSDG: {
            de: 'Filtern Sie Kacheln nach SDG-Zielen',
            en: 'Filter tiles by SDG goals',
        },
        searchInput: {
            de: 'Durchsuchen Sie Kacheln nach Titel oder Beschreibung',
            en: 'Search tiles by title or description',
        },
        tileInfoButton: {
            de: 'Zeigt weitere Informationen zu dieser Kachel',
            en: 'Shows additional information about this tile',
        },
        yearSlider: {
            de: 'Wählen Sie ein Jahr aus, um die Werte für dieses Jahr anzuzeigen',
            en: 'Select a year to display values for that year',
        },
        indicatorValue: {
            de: 'Aktueller Wert des Indikators',
            en: 'Current value of the indicator',
        },
        overlayClose: {
            de: 'Overlay schließen',
            en: 'Close overlay',
        },
    },
    helpOverlays: {
        filters: {
            de: '<h3>Filter verwenden</h3><p>Sie können Kacheln nach verschiedenen Kriterien filtern:</p><ul><li><strong>Handlungsdimensionen:</strong> Filtern Sie nach Gerechtigkeit, Produktivität oder Grün</li><li><strong>Handlungsfelder:</strong> Filtern Sie nach spezifischen Handlungsfeldern</li><li><strong>SDG-Ziele:</strong> Filtern Sie nach den Nachhaltigkeitszielen der UN</li></ul><p>Sie können auch die Suchfunktion verwenden, um nach Titeln oder Beschreibungen zu suchen.</p>',
            en: '<h3>Using Filters</h3><p>You can filter tiles by various criteria:</p><ul><li><strong>Action Dimensions:</strong> Filter by Justice, Productivity, or Green</li><li><strong>Action Fields:</strong> Filter by specific action fields</li><li><strong>SDG Goals:</strong> Filter by UN sustainability goals</li></ul><p>You can also use the search function to search by titles or descriptions.</p>',
        },
        tiles: {
            de: '<h3>Kacheln verstehen</h3><p>Kacheln zeigen Indikatoren mit Zeitreihendaten. Sie können:</p><ul><li>Den Jahr-Schieberegler verwenden, um verschiedene Jahre anzuzeigen</li><li>Auf das Info-Icon klicken, um weitere Details zu sehen</li><li>Die Indikatoren zeigen aktuelle Werte und Änderungen</li></ul>',
            en: '<h3>Understanding Tiles</h3><p>Tiles display indicators with time-series data. You can:</p><ul><li>Use the year slider to display different years</li><li>Click the info icon to see more details</li><li>Indicators show current values and changes</li></ul>',
        },
    },
    inlineHelp: {
        search: {
            de: '<p>Die Suchfunktion durchsucht Titel und Beschreibungen der Kacheln. Die Suche ist nicht case-sensitive und kann mit Filtern kombiniert werden.</p>',
            en: '<p>The search function searches tile titles and descriptions. The search is case-insensitive and can be combined with filters.</p>',
        },
    },
};

export const useHelpStore = defineStore('help', {
    state: () => ({
        currentContext: null,
        helpOverlayOpen: false,
        helpContent: { ...defaultHelpContent },
        loading: false,
    }),
    actions: {
        /**
         * Set the current help context
         * @param {string} context - The context identifier (e.g., 'filters', 'tiles', 'search')
         */
        setContext(context) {
            this.currentContext = context;
        },
        
        /**
         * Open help overlay for a specific context
         * @param {string} context - The context identifier
         */
        openHelpOverlay(context) {
            this.currentContext = context;
            this.helpOverlayOpen = true;
        },
        
        /**
         * Close the help overlay
         */
        closeHelpOverlay() {
            this.helpOverlayOpen = false;
        },
        
        /**
         * Toggle help overlay for a specific context
         * @param {string} context - The context identifier
         */
        toggleHelpOverlay(context) {
            if (this.helpOverlayOpen && this.currentContext === context) {
                this.closeHelpOverlay();
            } else {
                this.openHelpOverlay(context);
            }
        },
        
        /**
         * Get help content for a specific context and type
         * @param {string} context - The context identifier
         * @param {string} type - The help type ('tooltips', 'helpOverlays', 'inlineHelp')
         * @param {string} locale - The locale ('de' or 'en')
         * @returns {string|null} The help content or null if not found
         */
        getHelpForContext(context, type = 'tooltips', locale = 'de') {
            // Normalize type to match helpContent keys (plural forms)
            const typeMap = {
                'tooltip': 'tooltips',
                'helpOverlay': 'helpOverlays',
                'inlineHelp': 'inlineHelp',
            };
            const normalizedType = typeMap[type] || type;
            
            const content = this.helpContent[normalizedType]?.[context];
            if (!content) return null;
            
            if (typeof content === 'string') {
                return content;
            }
            
            if (typeof content === 'object' && (content[locale] || content.de || content.en)) {
                return content[locale] || content.de || content.en;
            }
            
            return null;
        },
        
        /**
         * Load help content from API
         * This should be called when the app initializes or when branding config is loaded
         */
        async loadHelpContent() {
            this.loading = true;
            try {
                const apiUrl = window.APP_URL || '';
                // Try to load from branding API first
                const res = await fetch(
                    apiUrl + '/api/config/branding',
                    { credentials: 'include' },
                );
                
                if (res.ok) {
                    const json = await res.json();
                    // If help content is provided in the API response, deep merge it with defaults
                    // This preserves nested defaults (e.g., tooltips) when API returns partial data
                    if (json.data?.help_content) {
                        this.helpContent = deepMerge(defaultHelpContent, json.data.help_content);
                    }
                }
            } catch (error) {
                logWarn('Failed to load help content from API, using defaults:', error);
                // Keep default content on error
            } finally {
                this.loading = false;
            }
        },
        
        /**
         * Get tooltip text for a specific key
         * @param {string} key - The tooltip key
         * @param {string} locale - The locale ('de' or 'en')
         * @returns {string|null} The tooltip text or null if not found
         */
        getTooltip(key, locale = 'de') {
            return this.getHelpForContext(key, 'tooltips', locale);
        },
        
        /**
         * Get help overlay content for a specific context
         * @param {string} context - The context identifier
         * @param {string} locale - The locale ('de' or 'en')
         * @returns {string|null} The help overlay content or null if not found
         */
        getHelpOverlayContent(context, locale = 'de') {
            return this.getHelpForContext(context, 'helpOverlays', locale);
        },
        
        /**
         * Get inline help content for a specific context
         * @param {string} context - The context identifier
         * @param {string} locale - The locale ('de' or 'en')
         * @returns {string|null} The inline help content or null if not found
         */
        getInlineHelp(context, locale = 'de') {
            return this.getHelpForContext(context, 'inlineHelp', locale);
        },
    },
});

