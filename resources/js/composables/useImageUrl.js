import { computed } from 'vue';
import { getApiBaseUrl } from '../utils/api';

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

        const apiUrl = getApiBaseUrl();

        // If already an absolute path (e.g. /storage/...), resolve against current origin
        if (imagePath.startsWith('/')) {
            return `${apiUrl}${imagePath}`;
        }
        
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
