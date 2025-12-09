<template>
    <div class="page-view">
        <!-- Loading state -->
        <div v-if="loading" class="container text-center py-10" role="status" aria-live="polite">
            <p>{{ locale === 'en' ? 'Loading page…' : 'Lade Seite…' }}</p>
        </div>
        
        <!-- Error state -->
        <div v-else-if="error" class="container py-10" role="alert" aria-live="assertive">
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <p class="text-red-800">
                    {{ locale === 'en' ? 'Error loading page:' : 'Fehler beim Laden der Seite:' }}
                    {{ error.message || error }}
                </p>
            </div>
        </div>
        
        <!-- Page content -->
        <div v-else-if="pageData" class="page-content">
            <!-- Page title (if not in blocks) -->
            <h1 v-if="pageData.title && !hasTitleBlock" class="page-title container py-6">
                {{ pageData.title }}
            </h1>
            
            <!-- Render blocks via BlockRenderer -->
            <BlockRenderer v-if="pageData.blocks && pageData.blocks.length > 0" :blocks="transformedBlocks" />
            
            <!-- Empty state -->
            <div v-else class="container py-10 text-center text-gray-500">
                <p>{{ locale === 'en' ? 'No content available' : 'Kein Inhalt verfügbar' }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, watch } from 'vue';
import BlockRenderer from '../BlockRenderer.vue';
import { usePagesStore } from '../../stores/pages';
import { setMetaTags } from '../../composables/useMeta';

const props = defineProps({
    pageData: {
        type: Object,
        default: null,
    },
    locale: {
        type: String,
        default: 'de',
    },
});

const pagesStore = usePagesStore();

// Computed properties
const loading = computed(() => pagesStore.loading);
const error = computed(() => pagesStore.error);

// Transform blocks from API format to BlockRenderer format
// API returns: { type: 'hero', props: {...} }
// BlockRenderer/Block components expect: { type: 'hero', props: {...} }
// So we keep the props structure as-is
const transformedBlocks = computed(() => {
    if (!props.pageData?.blocks) {
        return [];
    }
    
    return props.pageData.blocks
        .map((block) => {
            // Ensure block has the expected structure
            // API already provides { type, props }, so we use it as-is
            return {
                type: block.type,
                props: block.props || {},
            };
        });
});

// Check if blocks contain a title/hero block that would render the title
const hasTitleBlock = computed(() => {
    if (!props.pageData?.blocks) {
        return false;
    }
    return props.pageData.blocks.some(
        (block) => block.type === 'hero' || block.type === 'intro-text'
    );
});

// Update meta tags when page data changes
watch(
    () => props.pageData,
    (newPageData) => {
        if (newPageData) {
            updateMetaTags(newPageData);
        }
    },
    { immediate: true }
);

function updateMetaTags(pageData) {
    const apiUrl = typeof window !== 'undefined' && window.APP_URL 
        ? window.APP_URL 
        : '';
    const currentUrl = typeof window !== 'undefined' ? window.location.href : '';
    
    // Resolve image URL (meta.image might already include /storage/ or be a relative path)
    const imageUrl = pageData.meta?.image 
        ? (pageData.meta.image.startsWith('http') 
            ? pageData.meta.image 
            : `${apiUrl}${pageData.meta.image}`)
        : null;
    
    setMetaTags({
        title: pageData.title,
        description: pageData.meta?.description,
        image: imageUrl,
        url: currentUrl,
        og: {
            title: pageData.title,
            description: pageData.meta?.description,
            image: imageUrl,
            type: 'website',
        },
        twitter: {
            card: 'summary_large_image',
            title: pageData.title,
            description: pageData.meta?.description,
            image: imageUrl,
        },
    });
}
</script>

<style scoped>
.page-view {
    min-height: 50vh;
}

.page-title {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 2rem;
}
</style>
