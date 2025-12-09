import { computed } from 'vue';

/**
 * Composable for resolving image URLs
 * Handles both full URLs and storage paths
 * @param {import('vue').Ref|import('vue').ComputedRef} imageProp - Image prop (can be string or array)
 * @param {boolean} useStoragePath - Whether to prepend /storage/ (default: true)
 * @returns {import('vue').ComputedRef<string|null>} Resolved image URL
 */
export function useImageUrl(imageProp, useStoragePath = true) {
    return computed(() => {
        if (!imageProp.value) return null;
        
        const imagePath = Array.isArray(imageProp.value) 
            ? imageProp.value[0] 
            : imageProp.value;
        
        if (!imagePath) return null;
        
        // If already a full URL, return as-is
        if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
            return imagePath;
        }
        
        // Get API URL with SSR guard
        const apiUrl = typeof window !== 'undefined' && window.APP_URL 
            ? window.APP_URL 
            : '';
        
        // If useStoragePath is true, prepend /storage/, otherwise use path as-is
        if (useStoragePath) {
            const normalizedPath = imagePath.startsWith('/') ? imagePath.slice(1) : imagePath;
            return `${apiUrl}/storage/${normalizedPath}`;
        } else {
            // Path might already include /storage/ or be a relative path
            return `${apiUrl}${imagePath}`;
        }
    });
}
