/**
 * Centralized locale configuration and resolution utilities
 */

export const supportedLocales = ['de', 'en'];
export const defaultLocale = 'de';

/**
 * Resolve locale from various sources
 * Priority: localStorage > browser language > default
 * @returns {string} Resolved locale ('de' or 'en')
 */
export function resolveLocale() {
    if (typeof window === 'undefined') {
        return defaultLocale;
    }
    
    // 1. Try localStorage
    const stored = localStorage.getItem('locale');
    if (stored && supportedLocales.includes(stored)) {
        return stored;
    }
    
    // 2. Fallback to browser language (with SSR guard)
    if (typeof navigator !== 'undefined' && typeof navigator.language === 'string') {
        return navigator.language.startsWith('en') ? 'en' : defaultLocale;
    }
    
    // 3. Default fallback
    return defaultLocale;
}
