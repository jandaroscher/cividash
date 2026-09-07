<template>
  <div class="page-view">
    <!-- Loading state -->
    <div
      v-if="loading"
      class="container text-center py-10"
      role="status"
      aria-live="polite"
    >
      <p>{{ locale === 'en' ? 'Loading page…' : 'Lade Seite…' }}</p>
    </div>
        
    <!-- Error state -->
    <div
      v-else-if="error"
      class="container py-10"
      role="alert"
      aria-live="assertive"
    >
      <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <p class="text-red-800">
          {{ locale === 'en' ? 'Error loading page:' : 'Fehler beim Laden der Seite:' }}
          {{ error.message || error }}
        </p>
      </div>
    </div>
        
    <!-- Page content -->
    <div
      v-else-if="pageData"
      class="page-content"
    >
      <!-- Render blocks via BlockRenderer -->
      <BlockRenderer
        v-if="pageData.blocks && pageData.blocks.length > 0"
        :blocks="transformedBlocks"
      />
            
      <!-- Empty state -->
      <div
        v-else
        class="container py-10 text-center text-gray-500"
      >
        <p>{{ locale === 'en' ? 'No content available' : 'Kein Inhalt verfügbar' }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useHead, useSeoMeta } from '@unhead/vue';
import BlockRenderer from '../BlockRenderer.vue';
import { usePagesStore } from '../../stores/pages';
import { getApiBaseUrl } from '../../utils/api';

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

// Reactive meta tag management via @unhead/vue
const apiUrl = getApiBaseUrl();

const metaTitle = computed(() => {
    if (!props.pageData) return '';
    return props.pageData.meta?.title || props.pageData.title || '';
});

const metaDescription = computed(() => {
    if (!props.pageData) return '';
    return props.pageData.meta?.description || '';
});

const metaImage = computed(() => {
    const image = props.pageData?.meta?.image;
    if (!image) return null;
    return image.startsWith('http') ? image : `${apiUrl}${image}`;
});

const currentUrl = computed(() =>
    typeof window !== 'undefined' ? window.location.href : '',
);

useHead({
    title: metaTitle,
});

useSeoMeta({
    description: metaDescription,
    ogType: 'website',
    ogTitle: metaTitle,
    ogDescription: metaDescription,
    ogImage: metaImage,
    ogUrl: currentUrl,
    twitterCard: 'summary_large_image',
    twitterTitle: metaTitle,
    twitterDescription: metaDescription,
    twitterImage: metaImage,
});
</script>

<style scoped>
.page-view {
    min-height: 50vh;
}

</style>
