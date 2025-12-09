<template>
    <footer 
        class="mt-12 pt-9 pb-8 md:pt-11 md:pb-10"
        :style="{ backgroundColor: brandingStore.footerBackgroundColor || '#E5E7EB' }"
    >
        <div class="container text-center md:text-left">
            <!-- Footer Navigation -->
            <nav v-if="footerStore.footerNavigationItems.length > 0" class="mb-9" aria-label="Footer navigation">
                <!-- Single Row Layout -->
                <div 
                    v-if="layoutType === 'single-row'"
                    class="md:flex flex-wrap md:justify-center md:space-x-5 xl:space-x-0 xl:grid xl:grid-cols-6 space-y-5 md:space-y-0"
                    style="color: var(--text-primary-color, #000000);"
                >
                    <RouterLink
                        v-for="(item, index) in footerStore.footerNavigationItems"
                        :key="index"
                        :to="item.url"
                        class="transition-colors duration-200 footer-link"
                        style="color: var(--link-color, #E30613);"
                    >
                        {{ item.label }}
                    </RouterLink>
                </div>

                <!-- Multi-Column Layout -->
                <div 
                    v-else-if="layoutType === 'multi-column'"
                    :class="`grid grid-cols-1 footer-grid-multi-column gap-5`"
                    :style="`grid-template-columns: repeat(${cols}, minmax(0, 1fr)); color: var(--text-primary-color, #000000);`"
                >
                    <div
                        v-for="(item, index) in footerStore.footerNavigationItems"
                        :key="index"
                    >
                        <RouterLink
                            :to="item.url"
                            class="transition-colors duration-200 footer-link"
                            style="color: var(--link-color, #E30613);"
                        >
                            {{ item.label }}
                        </RouterLink>
                    </div>
                </div>

                <!-- Grid Layout -->
                <div 
                    v-else-if="layoutType === 'grid'"
                    :class="`grid grid-cols-1 sm:grid-cols-2 footer-grid-grid gap-5`"
                    :style="`grid-template-columns: repeat(${cols}, minmax(0, 1fr)); color: var(--text-primary-color, #000000);`"
                >
                    <div
                        v-for="(item, index) in footerStore.footerNavigationItems"
                        :key="index"
                    >
                        <RouterLink
                            :to="item.url"
                            class="transition-colors duration-200 footer-link"
                            style="color: var(--link-color, #E30613);"
                        >
                            {{ item.label }}
                        </RouterLink>
                    </div>
                </div>
            </nav>

            <!-- Social Links -->
            <div 
                v-if="footerStore.socialLinksEnabled && footerStore.socialLinks.length > 0"
                class="flex flex-wrap space-x-5 justify-center md:justify-start xl:justify-end mb-4"
            >
                <template v-for="(social, index) in footerStore.socialLinks" :key="index">
                    <a
                        v-if="social.link && social.icon"
                        :href="social.link"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="transition-colors duration-200 footer-link"
                        style="color: var(--link-color, #E30613);"
                        :aria-label="social.title || (locale === 'en' ? 'Social link' : 'Social Media Link')"
                        :title="social.title || (locale === 'en' ? 'Social link' : 'Social Media Link')"
                    >
                        <img 
                            :src="getSocialIconUrl(social.icon)" 
                            :alt="social.title || (locale === 'en' ? 'Social link' : 'Social Media Link')" 
                            class="w-6 h-6"
                        />
                    </a>
                </template>
            </div>

            <!-- Copyright -->
            <div 
                class="mt-4 text-sm text-center md:text-left"
                style="color: var(--text-secondary-color, #4B5563);"
            >
                <span v-if="copyrightTextFormatted">{{ copyrightTextFormatted }}</span>
                <span v-else>&copy; {{ currentYear }} {{ siteName }}</span>
            </div>
        </div>
    </footer>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useFooterStore } from '../../stores/footer';
import { useBrandingStore } from '../../stores/branding';
import { useLocale } from '../../composables/useLocale';
import { getApiBaseUrl } from '../../utils/api';

const footerStore = useFooterStore();
const brandingStore = useBrandingStore();
const { currentLocale: locale } = useLocale();

const siteName = computed(() => headerStore.siteName);

const currentYear = computed(() => new Date().getFullYear());

// Map layout types (API might return 'columns' or 'simple', but Blade uses 'single-row', 'multi-column', 'grid')
const layoutType = computed(() => {
    const apiType = footerStore.layoutType;
    // Map API types to Blade template types
    if (apiType === 'simple' || apiType === 'single-row') {
        return 'single-row';
    } else if (apiType === 'columns' || apiType === 'multi-column') {
        return 'multi-column';
    } else if (apiType === 'grid') {
        return 'grid';
    }
    // Default to single-row
    return 'single-row';
});

const cols = computed(() => {
    return Math.min(footerStore.columns || 3, 12);
});

// Format copyright text (replace {year} and {site_name})
const copyrightTextFormatted = computed(() => {
    if (!footerStore.copyrightText) {
        return null;
    }
    return footerStore.copyrightText
        .replace(/{year}/g, currentYear.value.toString())
        .replace(/{site_name}/g, siteName.value);
});

// Compute image URLs for all social icons at top-level (using same logic as useImageUrl)
const socialIconUrlMap = computed(() => {
    const map = new Map();
    const apiUrl = getApiBaseUrl();
    
    footerStore.socialLinks.forEach((social) => {
        if (social.icon) {
            let imageUrl = '';
            const iconPath = social.icon;
            
            // If already a full URL, return as-is
            if (iconPath.startsWith('http://') || iconPath.startsWith('https://')) {
                imageUrl = iconPath;
            } else {
                // Strip leading slash first
                let normalizedPath = iconPath.startsWith('/') ? iconPath.slice(1) : iconPath;
                
                // If path already starts with "storage/", append directly to apiUrl
                // Otherwise, prefix with "storage/"
                if (normalizedPath.startsWith('storage/')) {
                    imageUrl = `${apiUrl}/${normalizedPath}`;
                } else {
                    imageUrl = `${apiUrl}/storage/${normalizedPath}`;
                }
            }
            
            map.set(iconPath, imageUrl);
        }
    });
    
    return map;
});

// Get social icon URL from precomputed map
function getSocialIconUrl(iconPath) {
    if (!iconPath) return '';
    return socialIconUrlMap.value.get(iconPath) || '';
}


// Watch locale changes and refetch footer data
watch(
    () => locale.value,
    (newLocale) => {
        footerStore.fetchConfig(newLocale);
    },
    { immediate: true }
);
</script>

<style scoped>
/* Multi-column grid styles */
@media (min-width: 768px) {
    .footer-grid-multi-column {
        display: grid;
    }
    .footer-grid-grid {
        display: grid;
    }
}

/* Footer link hover styles */
footer a.footer-link:hover {
    color: var(--nav-hover-color) !important;
}
</style>
