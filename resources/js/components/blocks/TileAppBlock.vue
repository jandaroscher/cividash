<template>
  <div class="tile-app-block">
    <Filter
      v-if="!tilesStore.loading && !tilesStore.error && (showSearch || showFilter || blockHeading)"
      :show-search="showSearch"
      :show-filter="showFilter"
      :heading="blockHeading"
    />

    <div
      v-if="!tilesStore.loading && !tilesStore.error && tilesStore.tiles.length > 0"
      class="container"
    >
      <p class="text-theme-base text-gray-400 flex flex-row gap-2 mb-5 ml-auto justify-end mt-10 sm:mt-0">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="24"
          height="25"
          viewBox="0 0 24 25"
          fill="none"
          aria-hidden="true"
        >
          <path
            d="M7 17L17 7"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
          <path
            d="M7 7H17V17"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
        <span>{{ effectiveLocale === 'en' ? 'Change from previous year' : 'Veränderung zum Vorjahr' }}</span>
      </p>
    </div>

    <div
      v-if="tilesStore.loading"
      class="container text-center py-10"
      role="status"
      aria-live="polite"
      aria-label="Loading tiles"
    >
      {{ effectiveLocale === 'en' ? 'Loading tiles…' : 'Lade Tiles…' }}
    </div>
    <div
      v-else-if="tilesStore.error"
      class="container text-accent-dark bg-red-50 border border-red-200 rounded-lg p-4"
      role="alert"
      aria-live="assertive"
    >
      {{ effectiveLocale === 'en' ? 'Error loading tiles:' : 'Fehler beim Laden der Tiles:' }} {{ tilesStore.error.message }}
    </div>
    <Cards v-else :selected-tile-ids="selectedTileIds" />
    <Overlay />
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch, computed } from 'vue';
import { useRoute } from 'vue-router';
import Cards from '../Cards.vue';
import Filter from '../Filter.vue';
import Overlay from '../overlay/Overlay.vue';
import { useTilesStore } from '../../stores/tiles';
import { useOverlayStore } from '../../stores/overlay';
import { useFilterStore } from '../../stores/filter';

const props = defineProps({
    block: {
        type: Object,
        required: true,
    },
});

// Extract props from block
const selectedTileIds = computed(() => {
    const ids = props.block.props?.tiles;
    return Array.isArray(ids) && ids.length > 0 ? ids : [];
});
const hasSelectedTiles = computed(() => selectedTileIds.value.length > 0);
const hideFilters = computed(() => selectedTileIds.value.length === 1);
const showSearch = computed(() => !hideFilters.value && props.block.props?.show_search !== false);
const showFilter = computed(() => !hideFilters.value && props.block.props?.show_filter !== false);
const blockHeading = computed(() => {
    return props.block.props?.heading || null;
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

// Set locale in tiles store
watch(
    effectiveLocale,
    (newLocale) => {
        tilesStore.setLocale(newLocale);
    },
    { immediate: true }
);

// Reload tiles when locale changes
watch(
    effectiveLocale,
    (newLocale, oldLocale) => {
        // Only reload when locale actually changed (not on initial mount)
        if (newLocale !== oldLocale && oldLocale !== undefined) {
            tilesStore.fetchAll(newLocale);
        }
    },
    { immediate: false }
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
.tile-app-block {
    min-height: 50vh;
}
</style>
