<template>
    <div class="app-wrapper">
        <main class="overflow-x-hidden pt-7 md:pt-12">
            <Filter v-if="!tilesStore.loading && !tilesStore.error" />
            
            <div v-if="tilesStore.loading" class="container text-center py-10" role="status" aria-live="polite" aria-label="Loading tiles">
                {{ effectiveLocale === 'en' ? 'Loading tiles…' : 'Lade Tiles…' }}
            </div>
            <div v-else-if="tilesStore.error" class="container text-accent-dark bg-red-50 border border-red-200 rounded-lg p-4" role="alert" aria-live="assertive">
                {{ effectiveLocale === 'en' ? 'Error loading tiles:' : 'Fehler beim Laden der Tiles:' }} {{ tilesStore.error.message }}
            </div>
            <Cards v-else />
        </main>
        <Overlay />
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import Cards from './components/Cards.vue';
import Filter from './components/Filter.vue';
import Overlay from './components/overlay/Overlay.vue';
import { useBrandingStore } from './stores/branding';
import { useTilesStore } from './stores/tiles';

// Define props to accept attributes passed from islands.js
const props = defineProps({
    mode: {
        type: String,
        default: 'explore',
    },
    initialCategory: {
        type: String,
        default: null,
    },
    useMockData: {
        type: Boolean,
        default: false,
    },
    locale: {
        type: String,
        default: null,
    },
    tenantId: {
        type: [String, Number],
        default: null,
    },
    tenantSlug: {
        type: String,
        default: null,
    },
    pageSlug: {
        type: String,
        default: null,
    },
});

const branding = useBrandingStore();
const tilesStore = useTilesStore();

// Set locale from props or detect from browser
const effectiveLocale = props.locale || (navigator.language.startsWith('en') ? 'en' : 'de');
tilesStore.setLocale(effectiveLocale);

// fetch tiles once the component mounts
// Note: branding.fetch() is already called in app.js before mounting
onMounted(() => {
    tilesStore.fetchAll(effectiveLocale);
});
</script>

<style scoped>
.text-primary-color {
    color: var(--primary-color);
}
</style>

