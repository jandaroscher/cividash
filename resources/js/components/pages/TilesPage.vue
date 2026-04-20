<template>
    <div class="app-wrapper">
        <main class="overflow-x-hidden pt-7 md:pt-12">
            <Filter v-if="!tilesStore.loading && !tilesStore.error && (showSearch || showFilter)" :show-search="showSearch" :show-filter="showFilter" />
            
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
import { ref, onMounted, onBeforeUnmount, watch, computed } from 'vue';
import { useRoute } from 'vue-router';
import { useHead } from '@unhead/vue';
import Cards from '../Cards.vue';
import Filter from '../Filter.vue';
import Overlay from '../overlay/Overlay.vue';
import { useTilesStore } from '../../stores/tiles';
import { useOverlayStore } from '../../stores/overlay';
import { useFilterStore } from '../../stores/filter';

// Props for optional configuration
const props = defineProps({
    showSearch: {
        type: Boolean,
        default: true,
    },
    showFilter: {
        type: Boolean,
        default: true,
    },
});

const route = useRoute();
const tilesStore = useTilesStore();
const overlayStore = useOverlayStore();
const filterStore = useFilterStore();

// Get locale from route meta or detect from browser
const effectiveLocale = computed(() => {
    if (route.meta?.locale) {
        return route.meta.locale;
    }
    // Fallback to browser language or store locale
    if (tilesStore.locale) {
        return tilesStore.locale;
    }
    if (typeof navigator !== 'undefined' && typeof navigator.language === 'string') {
        return navigator.language.startsWith('en') ? 'en' : 'de';
    }
    return 'de';
});

// Set page title for the tiles listing
useHead({
    title: computed(() => effectiveLocale.value === 'en' ? 'Tiles' : 'Kacheln'),
});

// Set locale in tiles store
watch(
    effectiveLocale,
    (newLocale) => {
        tilesStore.setLocale(newLocale);
    },
    { immediate: true }
);

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
const popStateHandler = ref(null);

// Fetch tiles once the component mounts
onMounted(() => {
    // Restore filter state from URL before fetching tiles
    filterStore.restoreFromUrl();
    
    tilesStore.fetchAll(effectiveLocale.value);
    
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

