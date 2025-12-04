import { computed, watch } from 'vue';
import { useHelpStore } from '../stores/help';
import { useLocale } from './useLocale';

/**
 * Provide reactive access and helpers for contextual help (tooltips, overlays, inline help).
 *
 * @returns {Object} An API for managing and querying help state and content.
 * @property {import('vue').ComputedRef<string|null>} currentContext - Reactive current context identifier from the help store.
 * @property {import('vue').ComputedRef<boolean>} helpOverlayOpen - Reactive boolean indicating if the help overlay is open.
 * @property {Function} getCurrentContext - Return the current context identifier or `null`.
 * @property {Function} getHelpForContext - (context: string, type?: string) ⇒ `string|null` — retrieve help content for a context and type using the current locale. Type defaults to 'tooltips'.
 * @property {Function} openHelp - (context: string) ⇒ void — open the help overlay for the given context.
 * @property {Function} closeHelp - () ⇒ void — close the help overlay.
 * @property {Function} toggleHelp - (context: string) ⇒ void — toggle the help overlay for the given context.
 * @property {Function} getTooltip - (key: string) ⇒ `string|null` — retrieve a localized tooltip string by key.
 * @property {Function} getHelpOverlayContent - (context: string) ⇒ `string|null` — retrieve localized overlay content for a context.
 * @property {Function} getInlineHelp - (context: string) ⇒ `string|null` — retrieve localized inline help for a context.
 * @property {Function} detectContextFromState - (state: Object) ⇒ `string|null` — detect a context identifier from a component state object (e.g., filters, overlay, search).
 * @property {Function} isFirstVisit - () ⇒ boolean — return `true` on first browser visit, otherwise `false` (pure, idempotent check).
 * @property {Function} markVisited - () ⇒ void — mark that the user has visited (sets `hasVisited` flag in localStorage).
 * @property {Function} markHelpShown - (context: string) ⇒ void — mark that help has been shown for the specified context in localStorage.
 * @property {Function} hasHelpBeenShown - (context: string) ⇒ boolean — return `true` if help has previously been shown for the specified context.
 */
export function useHelpContext() {
    const helpStore = useHelpStore();
    const { currentLocale } = useLocale();
    
    /**
     * Retrieve the current help context identifier.
     * @returns {string|null} The current help context identifier, or `null` if no context is set.
     */
    function getCurrentContext() {
        // Help context is set by components via the help store.
        return helpStore.currentContext;
    }
    
    /**
     * Get help content for a specific context
     * @param {string} context - The context identifier
     * @param {string} type - The help type ('tooltips', 'helpOverlays', 'inlineHelp')
     * @returns {string|null} The help content or null if not found
     */
    function getHelpForContext(context, type = 'tooltips') {
        return helpStore.getHelpForContext(context, type, currentLocale.value);
    }
    
    /**
     * Open the help overlay for the given context.
     * @param {string} context - Context identifier whose help overlay should be opened.
     */
    function openHelp(context) {
        helpStore.openHelpOverlay(context);
    }
    
    /**
     * Close the help overlay.
     */
    function closeHelp() {
        helpStore.closeHelpOverlay();
    }
    
    /**
     * Toggle help overlay for a specific context
     * @param {string} context - The context identifier
     */
    function toggleHelp(context) {
        helpStore.toggleHelpOverlay(context);
    }
    
    /**
     * Retrieve tooltip text for a given key in the current locale.
     * @param {string} key - The tooltip key.
     * @returns {string|null} The tooltip text for the given key in the current locale, or `null` if not found.
     */
    function getTooltip(key) {
        return helpStore.getTooltip(key, currentLocale.value);
    }
    
    /**
     * Retrieve the help overlay content for the given context in the current locale.
     * @param {string} context - Context identifier for which to fetch overlay help.
     * @returns {string|null} The localized help overlay content, or `null` if none exists.
     */
    function getHelpOverlayContent(context) {
        return helpStore.getHelpOverlayContent(context, currentLocale.value);
    }
    
    /**
     * Retrieve inline help content for a given context in the current locale.
     * @param {string} context - The help context identifier.
     * @returns {string|null} The inline help content for the context, or `null` if none exists.
     */
    function getInlineHelp(context) {
        return helpStore.getInlineHelp(context, currentLocale.value);
    }
    
    /**
     * Determine the current help context from a component state object.
     * @param {Object} state - Component state containing flags used to infer context (e.g. `filterActive`, `overlayOpen`, `searchActive`).
     * @returns {'filters'|'tiles'|'search'|null} The detected context: `'filters'`, `'tiles'`, or `'search'`; `null` if none detected.
     */
    function detectContextFromState(state) {
        if (!state) return null;
        
        // Example: Detect filter context
        if (state.filterActive) {
            return 'filters';
        }
        
        // Example: Detect overlay context
        if (state.overlayOpen) {
            return 'tiles';
        }
        
        // Example: Detect search context
        if (state.searchActive) {
            return 'search';
        }
        
        return null;
    }
    
    /**
     * Determine whether this is the user's first visit (pure, idempotent check).
     *
     * Returns `false` when not running in a browser environment or if the user has already visited.
     * This function does not modify any state; use `markVisited()` to record the visit.
     * @returns {boolean} `true` if this is the first visit, `false` otherwise.
     */
    function isFirstVisit() {
        if (typeof window === 'undefined') return false;
        
        const hasVisited = localStorage.getItem('hasVisited');
        return !hasVisited;
    }
    
    /**
     * Mark that the user has visited by setting a localStorage flag.
     * This is a no-op when not running in a browser environment.
     */
    function markVisited() {
        if (typeof window === 'undefined') return;
        
        localStorage.setItem('hasVisited', 'true');
    }
    
    /**
     * Record that help was shown for the given context by setting a localStorage flag.
     * This is a no-op when not running in a browser environment.
     * @param {string} context - Context identifier used as part of the storage key.
     */
    function markHelpShown(context) {
        if (typeof window === 'undefined') return;
        
        const key = `helpShown_${context}`;
        localStorage.setItem(key, 'true');
    }
    
    /**
     * Determine whether help has been shown for the given context.
     *
     * Returns false when not running in a browser environment.
     * @param {string} context - The context identifier used as part of the storage key.
     * @returns {boolean} `true` if help has been shown for the given context, `false` otherwise.
     */
    function hasHelpBeenShown(context) {
        if (typeof window === 'undefined') return false;
        
        const key = `helpShown_${context}`;
        return localStorage.getItem(key) === 'true';
    }
    
    return {
        // Current context
        currentContext: computed(() => helpStore.currentContext),
        helpOverlayOpen: computed(() => helpStore.helpOverlayOpen),
        
        // Functions
        getCurrentContext,
        getHelpForContext,
        openHelp,
        closeHelp,
        toggleHelp,
        getTooltip,
        getHelpOverlayContent,
        getInlineHelp,
        detectContextFromState,
        isFirstVisit,
        markVisited,
        markHelpShown,
        hasHelpBeenShown,
    };
}
