import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { supportedLocales, defaultLocale, resolveLocale as resolveLocaleUtil } from '../utils/locale';
import { useTilesStore } from '../stores/tiles';
import { usePagesStore } from '../stores/pages';
import { useHeaderStore } from '../stores/header';
import { useFooterStore } from '../stores/footer';

// Global locale state
const currentLocaleRef = ref(defaultLocale);

/**
 * Composable to get and manage the current locale
 * Supports router-based locale, localStorage persistence, and fallback to browser language
 */
export function useLocale() {
    const route = useRoute();
    const router = useRouter();
    const tilesStore = useTilesStore();
    const pagesStore = usePagesStore();
    const headerStore = useHeaderStore();
    const footerStore = useFooterStore();
    
    // Get locale from route meta, localStorage, or fallback
    const getLocale = () => {
        // 1. Try route meta (set by router guard)
        if (route.meta?.locale) {
            return route.meta.locale;
        }
        
        // 2. Try localStorage
        if (typeof window !== 'undefined') {
            const stored = localStorage.getItem('locale');
            if (stored && supportedLocales.includes(stored)) {
                return stored;
            }
        }
        
        // 3. Try tilesStore
        if (tilesStore.locale && supportedLocales.includes(tilesStore.locale)) {
            return tilesStore.locale;
        }
        
        // 4. Fallback to browser language using centralized utility
        return resolveLocaleUtil();
    };
    
    const currentLocale = computed(() => {
        return getLocale();
    });

    // Sync global ref separately
    watch(currentLocale, (newLocale) => {
        currentLocaleRef.value = newLocale;
    }, { immediate: true });
    
    /**
     * Helper function to build locale path from page data
     * @param {Object} pageData - Page metadata object
     * @param {string} targetLocale - Target locale ('de' or 'en')
     * @returns {string|null} Locale path or null if slug not found
     */
    const buildLocalePath = (pageData, targetLocale) => {
        const translatedSlug = typeof pageData.slug === 'string' 
            ? pageData.slug 
            : pageData.slug?.[targetLocale];
        
        if (!translatedSlug) {
            return null;
        }
        
        return targetLocale === 'en' ? `/en/${translatedSlug}` : `/${translatedSlug}`;
    };
    
    /**
     * Get translated slug for current page
     * @param {string} targetLocale - Target locale ('de' or 'en')
     * @returns {Promise<string|null>} Translated slug or null if not found
     */
    const getTranslatedSlug = async (targetLocale) => {
        // Early return for unsupported locales
        if (!supportedLocales.includes(targetLocale)) {
            return null;
        }

        try {
            // If we're on the home page
            if (route.path === '/' || route.path === '/en') {
                return targetLocale === 'en' ? '/en' : '/';
            }
            
            // Get current page ID from pagesStore
            const currentPage = pagesStore.currentPage;
            if (!currentPage || !currentPage.id) {
                // Try to find page by current slug
                const rawSlug = route.params.slug;
                const currentSlug = Array.isArray(rawSlug) ? rawSlug.join('/') : rawSlug;
                if (!currentSlug) {
                    return targetLocale === 'en' ? '/en' : '/';
                }
                
                // Fetch page list for current locale to find page ID
                const locale = getLocale();
                const pages = (await pagesStore.fetchPageList(locale)) || [];
                const pageMeta = pages.find((page) => {
                    const pageSlug = typeof page.slug === 'string' ? page.slug : page.slug?.[locale];
                    return pageSlug === currentSlug;
                });
                
                if (!pageMeta || !pageMeta.id) {
                    return null;
                }
                
                // Fetch page list for target locale to find translated slug
                const targetPages = (await pagesStore.fetchPageList(targetLocale)) || [];
                const targetPageMeta = targetPages.find((page) => page.id === pageMeta.id);
                
                if (!targetPageMeta) {
                    return null;
                }
                
                return buildLocalePath(targetPageMeta, targetLocale);
            }
            
            // We have current page ID, fetch translated slug
            const targetPages = (await pagesStore.fetchPageList(targetLocale)) || [];
            const targetPageMeta = targetPages.find((page) => page.id === currentPage.id);
            
            if (!targetPageMeta) {
                return null;
            }
            
            return buildLocalePath(targetPageMeta, targetLocale);
        } catch (error) {
            logError('Failed to resolve translated slug:', error);
            return null;
        }
    };
    
    /**
     * Set locale and update route if needed
     * @param {string} locale - Locale to set ('de' or 'en')
     * @param {boolean} updateRoute - Whether to update the route (default: true)
     */
    const setLocale = async (locale, updateRoute = true) => {
        let effectiveLocale = locale;
        if (!supportedLocales.includes(locale)) {
            logWarn(`Invalid locale: ${locale}. Using default: ${defaultLocale}`);
            effectiveLocale = defaultLocale;
        }
        
        // Update localStorage
        if (typeof window !== 'undefined') {
            localStorage.setItem('locale', effectiveLocale);
        }
        
        // Update tilesStore
        tilesStore.setLocale(effectiveLocale);
        
        // Update global ref
        currentLocaleRef.value = effectiveLocale;
        
        // Stores are reloaded by their respective watchers in Header.vue and Footer.vue
        // No need to fetch here to avoid duplicate API calls
        
        // Update route if needed
        if (updateRoute && router) {
            const currentPath = route.path;
            
            // Get translated slug for current page
            const translatedPath = await getTranslatedSlug(effectiveLocale);
            
            let newPath;
            if (translatedPath) {
                // Use translated path
                newPath = translatedPath;
            } else {
                // Fallback: build path with locale prefix
                if (effectiveLocale === 'de') {
                    // Remove /en prefix if present
                    newPath = currentPath.replace(/^\/en/, '') || '/';
                } else {
                    // Add /en prefix
                    if (currentPath === '/' || currentPath === '') {
                        newPath = '/en';
                    } else if (currentPath.startsWith('/en')) {
                        newPath = currentPath; // Already has /en
                    } else {
                        newPath = `/en${currentPath}`;
                    }
                }
            }
            
            // Only navigate if path actually changed
            if (newPath !== currentPath) {
                router.push(newPath).catch(() => {
                    // Ignore navigation failures (e.g. aborted/duplicate)
                });
            }
        }
    };
    
    return {
        currentLocale,
        setLocale,
        getTranslatedSlug,
        supportedLocales,
        defaultLocale,
        getLocale,
    };
}

// Re-export locale constants for convenience
export { supportedLocales, defaultLocale };

