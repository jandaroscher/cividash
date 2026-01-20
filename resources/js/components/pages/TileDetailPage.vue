<template>
    <div class="tile-detail-page">
        <NotFound v-if="isNotFound" />
        <div v-else-if="loading" class="container text-center py-10" role="status" aria-live="polite">
            <p>{{ messages.loading }}</p>
        </div>
        <div v-else-if="error" class="container py-10" role="alert" aria-live="assertive">
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <p class="text-red-800">
                    {{ messages.errorPrefix }} {{ error.message || error }}
                </p>
            </div>
        </div>
        <div v-else-if="tile" class="tile-detail-content">
            <OverlayHeader :tile="tile" @close="navigateBack" />
            <OverlayContent :tile="tile" />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import OverlayHeader from '../overlay/parts/Header.vue';
import OverlayContent from '../overlay/parts/Content.vue';
import NotFound from './NotFound.vue';
import { useTilesStore } from '../../stores/tiles';
import { setMetaTags } from '../../composables/useMeta';

const route = useRoute();
const router = useRouter();
const tilesStore = useTilesStore();

const locale = computed(() => {
    if (route.meta?.locale) {
        return route.meta.locale;
    }
    if (route.path.startsWith('/en')) {
        return 'en';
    }
    return 'de';
});

const slug = computed(() => route.params.slug || null);
const tile = ref(null);
const isNotFound = ref(false);

const loading = computed(() => tilesStore.loading);
const error = computed(() => tilesStore.error);

const messages = computed(() => ({
    loading: locale.value === 'en' ? 'Loading tile…' : 'Lade Kachel…',
    errorPrefix: locale.value === 'en' ? 'Error loading tile:' : 'Fehler beim Laden der Kachel:',
}));

async function loadTile() {
    if (!slug.value) {
        isNotFound.value = true;
        return;
    }

    isNotFound.value = false;
    tile.value = null;

    const data = await tilesStore.fetchBySlug(slug.value, locale.value, true);

    if (data) {
        tile.value = data;
        updateMetaTags(data);
    } else if (tilesStore.error?.status === 404) {
        isNotFound.value = true;
    }
}

function navigateBack() {
    const routeName = locale.value === 'en' ? 'tiles-en' : 'tiles';
    router.push({ name: routeName });
}

function getLocalizedValue(value) {
    if (!value) return '';
    if (typeof value === 'string') return value;
    return value[locale.value] || value.de || value.en || '';
}

function updateMetaTags(tileData) {
    const currentUrl = typeof window !== 'undefined' ? window.location.href : '';
    const fallbackTitle = getLocalizedValue(tileData.title);
    const metaTitle = tileData.meta?.title || fallbackTitle;
    const metaDescription = tileData.meta?.description || '';
    const metaImage = tileData.meta?.image || null;

    setMetaTags({
        title: metaTitle,
        description: metaDescription,
        image: metaImage,
        url: currentUrl,
        og: {
            title: metaTitle,
            description: metaDescription,
            image: metaImage,
            type: 'website',
        },
        twitter: {
            card: 'summary_large_image',
            title: metaTitle,
            description: metaDescription,
            image: metaImage,
        },
    });
}

onMounted(() => {
    loadTile();
});

watch(
    () => [slug.value, locale.value],
    () => {
        loadTile();
    }
);
</script>

<style scoped>
.tile-detail-page {
    min-height: 50vh;
}
</style>
