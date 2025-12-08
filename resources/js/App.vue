<template>
    <div class="app-wrapper">
        <main class="overflow-x-hidden pt-7 md:pt-12">
            <Filter v-if="!tilesStore.loading && !tilesStore.error && (props.showSearch || props.showFilter)" :show-search="props.showSearch" :show-filter="props.showFilter" />
            
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
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import Cards from './components/Cards.vue';
import Filter from './components/Filter.vue';
import Overlay from './components/overlay/Overlay.vue';
import { useBrandingStore } from './stores/branding';
import { useTilesStore } from './stores/tiles';
import { useOverlayStore } from './stores/overlay';
import { useFilterStore } from './stores/filter';

// Define props to accept attributes passed from islands.js
const props = defineProps({
    showSearch: {
        type: Boolean,
        default: true,
    },
    showFilter: {
        type: Boolean,
        default: true,
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
const overlayStore = useOverlayStore();
const filterStore = useFilterStore();

// Set locale from props or detect from browser
const effectiveLocale = props.locale || (navigator.language.startsWith('en') ? 'en' : 'de');
tilesStore.setLocale(effectiveLocale);

// Watch for tiles to be loaded, then check URL for deep-linking
watch(
    () => tilesStore.tiles,
    (tiles) => {
        if (tiles && tiles.length > 0 && !overlayStore.open) {
            // Try to open overlay from URL parameter
            overlayStore.openFromUrl(tiles);
        }
    },
    { immediate: true }
);

// Handle browser back/forward button - close overlay if tile parameter is removed
// Use ref to ensure proper cleanup on remount
const popStateHandler = ref(null);

// fetch tiles once the component mounts
// Note: branding.fetch() is already called in app.js before mounting
onMounted(() => {
    // Restore filter state from URL before fetching tiles
    filterStore.restoreFromUrl();
    
    tilesStore.fetchAll(effectiveLocale);
    
    // Remove any existing handler before adding a new one (prevents duplicates on remount)
    if (popStateHandler.value) {
        window.removeEventListener('popstate', popStateHandler.value);
    }
    
    // Setup popstate handler for browser back/forward button
    popStateHandler.value = () => {
        // Handle overlay state
        const tileSlug = new URLSearchParams(window.location.search).get('tile');
        if (!tileSlug && overlayStore.open) {
            // URL parameter was removed (e.g., via back button), close overlay
            overlayStore.closeOverlay();
        } else if (tileSlug && !overlayStore.open && tilesStore.tiles.length > 0) {
            // URL parameter was added (e.g., via forward button), open overlay
            overlayStore.openFromUrl(tilesStore.tiles);
        }
        
        // Handle filter state
        filterStore.restoreFromUrl();
    };
    
    window.addEventListener('popstate', popStateHandler.value);

});

onBeforeUnmount(() => {
    if (popStateHandler.value) {
        window.removeEventListener('popstate', popStateHandler.value);
        popStateHandler.value = null;
    }
});
</script>

<style scoped>
.text-primary-color {
    color: var(--primary-color);
}
</style>

