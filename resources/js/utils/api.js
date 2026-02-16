/**
 * Get the API base URL with SSR guard
 * @returns {string} API base URL or empty string if not available
 */
export function getApiBaseUrl() {
    return typeof window !== 'undefined' ? window.location.origin : '';
}
