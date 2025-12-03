import { computed } from 'vue';
import { useTilesStore } from '../stores/tiles';

/**
 * Composable to get the current locale
 * Uses the locale from tilesStore if available, otherwise falls back to browser language
 */
export function useLocale() {
    const tilesStore = useTilesStore();
    
    const currentLocale = computed(() => {
        // Always use the locale from tilesStore if it's set
        // This ensures consistency between tiles and filters
        if (tilesStore.locale) {
            return tilesStore.locale;
        }
        
        // Fallback to browser language only if store locale is not set
        return navigator.language.startsWith('en') ? 'en' : 'de';
    });
    
    return {
        currentLocale,
    };
}

