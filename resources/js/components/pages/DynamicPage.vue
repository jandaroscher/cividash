<template>
    <div class="dynamic-page">
        <PageView v-if="pageData" :page-data="pageData" :locale="locale" />
        <NotFound v-else-if="isNotFound" />
        <div v-else-if="loading" class="container text-center py-10">
            <p>{{ messages.loading }}</p>
        </div>
        <div v-else-if="error" class="container py-10">
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <p class="text-red-800">
                    {{ messages.errorPrefix }} {{ error.message || error }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { useRoute } from 'vue-router';
import PageView from './PageView.vue';
import NotFound from './NotFound.vue';
import { usePagesStore } from '../../stores/pages';

const route = useRoute();
const pagesStore = usePagesStore();

// Get locale from route meta or path
const locale = computed(() => {
    if (route.meta?.locale) {
        return route.meta.locale;
    }
    // Check if path starts with /en
    if (route.path.startsWith('/en')) {
        return 'en';
    }
    return 'de';
});

// Extract hardcoded locale strings to computed property
const messages = computed(() => ({
    loading: locale.value === 'en' ? 'Loading page…' : 'Lade Seite…',
    errorPrefix: locale.value === 'en' ? 'Error loading page:' : 'Fehler beim Laden der Seite:',
}));

// Get slug from route params (with /:slug+ it's an array of segments)
const slug = computed(() => {
    const rawSlug = route.params.slug;
    if (!rawSlug) return null;
    return Array.isArray(rawSlug) ? rawSlug.join('/') : rawSlug;
});

const pageData = ref(null);
const loading = ref(false);
const error = ref(null);
const isNotFound = ref(false);

async function loadPage() {
    if (!slug.value) {
        error.value = { message: 'No slug provided' };
        loading.value = false;
        return;
    }
    
    loading.value = true;
    error.value = null;
    isNotFound.value = false;
    pageData.value = null;
    
    try {
        const data = await pagesStore.fetchPageBySlug(slug.value, locale.value, true);
        if (data) {
            pageData.value = data;
        } else {
            // Check if it's a 404
            if (pagesStore.error?.status === 404 || pagesStore.isNotFound) {
                isNotFound.value = true;
            } else {
                error.value = pagesStore.error || { message: 'Failed to load page' };
            }
        }
    } catch (err) {
        error.value = err;
        logError('Failed to load page:', err);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadPage();
});

// Reload page when slug or locale changes
watch(
    () => [slug.value, route.meta?.locale],
    () => {
        loadPage();
    }
);
</script>

<style scoped>
.dynamic-page {
    min-height: 50vh;
}
</style>

