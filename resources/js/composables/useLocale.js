import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { supportedLocales, defaultLocale, resolveLocale as resolveLocaleUtil } from '../utils/locale';
import { useTilesStore } from '../stores/tiles';
import { usePagesStore } from '../stores/pages';
import { logError, logWarn } from '../lib/log.js';

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
     * Build the full hierarchical path for a page by walking up its parent chain
     * @param {Object} pageData - Page metadata object
     * @param {Array} allPages - All page metadata objects for the locale
     * @param {string} targetLocale - Target locale ('de' or 'en')
     * @returns {string|null} Full locale path (e.g. "/kontakt/testseite") or null
     */
    const buildLocalePath = (pageData, targetLocale, allPages = []) => {
        const getSlug = (page) =>
            typeof page.slug === 'string' ? page.slug : page.slug?.[targetLocale];

        // Build path segments by walking up the parent chain,
        // skipping root pages (slug "/" or "") which don't appear in the URL.
        const segments = [];
        let current = pageData;
        while (current) {
            const slug = getSlug(current);
            if (!slug) return null;
            if (slug !== '/' && slug !== '') {
                segments.unshift(slug);
            }
            current = current.parent_id
                ? allPages.find((p) => p.id === current.parent_id)
                : null;
        }

        if (segments.length === 0) return null;

        const fullSlug = segments.join('/');
        return targetLocale === 'en' ? `/en/${fullSlug}` : `/${fullSlug}`;
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
            let pageId = currentPage?.id;

            if (!pageId) {
                // Try to find page by current slug using hierarchical resolution
                const rawSlug = route.params.slug;
                const currentSlug = Array.isArray(rawSlug) ? rawSlug.join('/') : rawSlug;
                if (!currentSlug) {
                    return targetLocale === 'en' ? '/en' : '/';
                }

                const locale = getLocale();
                const pages = (await pagesStore.fetchPageList(locale)) || [];
                const pageMeta = pagesStore.resolvePageByPath(currentSlug, locale, pages);

                if (!pageMeta || !pageMeta.id) {
                    return null;
                }
                pageId = pageMeta.id;
            }

            // Fetch page list for target locale and build hierarchical path
            const targetPages = (await pagesStore.fetchPageList(targetLocale)) || [];
            const targetPageMeta = targetPages.find((page) => page.id === pageId);

            if (!targetPageMeta) {
                return null;
            }

            return buildLocalePath(targetPageMeta, targetLocale, targetPages);
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
                // Fallback: go to home page of target locale.
                // Don't prefix the current path — the slugs are likely
                // untranslated and would produce a broken URL.
                newPath = effectiveLocale === 'en' ? '/en' : '/';
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

