<template>
  <div class="container pt-6 md:mb-10">
    <VueFlexWaterfall
      v-if="filteredTiles.length > 0 && isReady"
      :key="`waterfall-${currentLocale}`"
      ref="waterfall"
      class="h-full max-w-[363px] mx-auto md:max-w-none md:mx-0"
      :style="{
        minHeight: '200px',
        visibility: isTransitioning ? 'hidden' : 'visible',
        // Exposed so TileCard (see its <style> block) can size each
        // tile to an exact fraction of this container's width per breakpoint.
        // vue-flex-waterfall only ever shrink-wraps its columns to content,
        // it never stretches them to fill the container, so without this the
        // grid drifts out of alignment with the search/filter header above it
        // whenever the tiles are narrower than their column.
        '--waterfall-col-desktop': colCount,
        '--waterfall-col-tablet': mdColCount,
      }"
      align-content="flex-start"
      :col="colCount"
      col-spacing="40"
      :break-at="{ 1280: mdColCount, 825: 1 }"
    >
      <component
        :is="tileComponent"
        v-for="tile in filteredTiles"
        :key="tile.id"
        :tile="tile"
      />
    </VueFlexWaterfall>
    <div
      v-if="visibleTilesCount === 0"
      class="text-center py-10 text-gray-600"
    >
      <template v-if="filterStore.searchQuery && filterStore.level2Filter?.key">
        {{ currentLocale === 'en' ? 'No tiles found for filter and search:' : 'Keine Tiles gefunden für Filter und Suche:' }} {{ filterStore.level2Filter.title }} / "{{ filterStore.searchQuery }}"
      </template>
      <template v-else-if="filterStore.searchQuery">
        {{ currentLocale === 'en' ? 'No tiles found for search:' : 'Keine Tiles gefunden für Suche:' }} "{{ filterStore.searchQuery }}"
      </template>
      <template v-else-if="filterStore.level2Filter?.key">
        {{ currentLocale === 'en' ? 'No tiles found for filter:' : 'Keine Tiles gefunden für Filter:' }} {{ filterStore.level2Filter.title }}
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { VueFlexWaterfall } from 'vue-flex-waterfall';
import { resolveTileComponent } from '../lib/componentRegistry';
import { useTilesStore } from '../stores/tiles';
import { useFilterStore } from '../stores/filter';
import { useTenantStore } from '../stores/tenant';
import { useLocale } from '../composables/useLocale';

const props = defineProps({
    selectedTileIds: {
        type: Array,
        default: () => [],
    },
});

const tilesStore = useTilesStore();
const filterStore = useFilterStore();
const tenantStore = useTenantStore();
const { currentLocale } = useLocale();

// Resolve the tile component for the active theme (theme-slug keyed override,
// else default TileCard). This wires the override registry into the real grid.
const tileComponent = computed(() => resolveTileComponent(tenantStore.themeSlug));

const waterfall = ref(null);
// Column counts are fixed per breakpoint (desktop=3, tablet=2) regardless of
// how many tiles are visible, so tiles keep a fixed 1/3 (desktop) / 1/2
// (tablet) width even when only 1-2 tiles are shown. The responsive
// reduction to fewer columns on narrower viewports is still handled entirely
// by the `:break-at="{ 1280: mdColCount, 825: 1 }"` prop below.
const colCount = ref(3);
const mdColCount = ref(2);
const isReady = ref(false); // Control when waterfall renders to prevent MutationObserver errors
const isTransitioning = ref(false); // Hide waterfall during filter transitions to prevent flicker
let resizeObserver = null;
let layoutUpdateTimeout = null;
let refreshLayoutTimeout = null; // Debounce filter changes to prevent flicker
const cardHeights = new Map(); // Track card heights to detect actual changes

// Function to check if a tile matches the search query
function matchesSearch(tile, searchQuery) {
    if (!searchQuery || !searchQuery.trim()) {
        return true;
    }
    
    const query = searchQuery.trim().toLowerCase();
    const locale = currentLocale.value;
    
    // Search in title
    const title = tile.title?.[locale] || tile.title?.de || tile.title || '';
    if (typeof title === 'string' && title.toLowerCase().includes(query)) {
        return true;
    }
    
    // Search in description
    const description = tile.description?.[locale] || tile.description?.de || tile.description || '';
    if (typeof description === 'string' && description.toLowerCase().includes(query)) {
        return true;
    }
    
    return false;
}

// Function to check if a tile should be visible based on current filter
function isTileVisible(tile) {
    // First narrow down to the selected tile IDs (if any are specified).
    // Compare as strings since Filament stores IDs as strings in JSON.
    // This only restricts the base set of tiles - search and category
    // filters below still apply on top of it.
    if (props.selectedTileIds.length > 0) {
        const isSelected = props.selectedTileIds.some(id => String(id) === String(tile.id));
        if (!isSelected) {
            return false;
        }
    }

    // Then check search query
    if (!matchesSearch(tile, filterStore.searchQuery)) {
        return false;
    }

    // Then check filter
    if (!filterStore.level2Filter?.key) {
        return true;
    }

    const filterType = filterStore.level1Filter;
    const filterKey = filterStore.level2Filter.key;

    const categories = tile.categories || [];
    if (Array.isArray(categories) && filterType) {
        const normalizedFilterKey = String(filterKey ?? '');
        return categories.some(category => {
            const groupKey = category.group?.key;
            if (groupKey !== filterType) {
                return false;
            }
            const categoryIdentifier = String(category.id ?? category.key ?? '');
            const categoryKey = String(category.key ?? '');
            return categoryIdentifier === normalizedFilterKey || categoryKey === normalizedFilterKey;
        });
    }

    return true;
}

const filteredTiles = computed(() => {
    return tilesStore.tiles.filter(tile => isTileVisible(tile));
});

// Keep filteredTiles for counting visible tiles
const visibleTilesCount = computed(() => {
    return filteredTiles.value.length;
});

// Debounced layout update function using requestAnimationFrame for smoother updates
function updateLayout() {
    if (layoutUpdateTimeout) {
        cancelAnimationFrame(layoutUpdateTimeout);
    }
    
    // Use requestAnimationFrame for smoother, batched updates
    layoutUpdateTimeout = requestAnimationFrame(() => {
        if (waterfall.value && tilesStore.tiles.length > 0) {
            try {
                waterfall.value.updateOrder();
            } catch (error) {
                logError('Error updating waterfall layout:', error);
            }
        }
    });
}

watch(
    () => filterStore.level2Filter,
    (filter) => {
        // Hide waterfall immediately to prevent layout flash
        isTransitioning.value = true;
        refreshLayout(filter);
    },
    { deep: true }
);

watch(
    () => filterStore.searchQuery,
    () => {
        // Hide waterfall immediately to prevent layout flash
        isTransitioning.value = true;
        refreshLayout(filterStore.level2Filter);
    }
);

watch(
    filteredTiles,
    (newTiles, oldTiles) => {
        // Only transition if tile count changed significantly (filter change, not initial load)
        if (oldTiles && oldTiles.length !== newTiles.length) {
            isTransitioning.value = true;
        }
        refreshLayout(filterStore.level2Filter);
    }
);

function setupResizeObserver() {
    // Improved null checks to prevent MutationObserver errors during locale switch
    if (!waterfall.value || !waterfall.value.$el) {
        return;
    }
    
    const container = waterfall.value.$el;
    if (!(container instanceof Node)) {
        return;
    }
    
    // Disconnect existing observer if any
    if (resizeObserver) {
        resizeObserver.disconnect();
    }
    
    // Create ResizeObserver to watch for tile height changes
    // This handles lazy-loaded images and Lottie animations
    resizeObserver = new ResizeObserver((entries) => {
        let hasActualChange = false;
        
        entries.forEach(entry => {
            const card = entry.target;
            const currentHeight = entry.contentRect.height;
            const previousHeight = cardHeights.get(card);
            
            // Only update if height actually changed (more than 1px difference to account for rounding)
            if (previousHeight === undefined || Math.abs(currentHeight - previousHeight) > 1) {
                cardHeights.set(card, currentHeight);
                hasActualChange = true;
            }
        });
        
        if (hasActualChange) {
            updateLayout();
        }
    });
    
    // Observe all tile cards for size changes
    const tileCards = container.querySelectorAll('.shadow-card');
    tileCards.forEach((card) => {
        // Store initial height
        cardHeights.set(card, card.offsetHeight);
        resizeObserver.observe(card);
    });
}

function setupImageLoadListeners() {
    // Improved null checks to prevent errors during locale switch
    if (!waterfall.value || !waterfall.value.$el) {
        return;
    }
    
    const container = waterfall.value.$el;
    if (!(container instanceof Node)) {
        return;
    }
    
    // Listen for image load events that might change tile heights
    const images = container.querySelectorAll('img[loading="lazy"]');
    images.forEach((img) => {
        if (!img.complete) {
            const onLoad = () => {
                updateLayout();
                img.removeEventListener('load', onLoad);
                img.removeEventListener('error', onLoad);
            };
            img.addEventListener('load', onLoad, { once: true });
            img.addEventListener('error', onLoad, { once: true });
        }
    });
}

onMounted(() => {
    // Wait for DOM to be ready before allowing waterfall to render
    nextTick(() => {
        isReady.value = true;
        
        nextTick(() => {
            if (tilesStore.tiles.length > 0) {
                refreshLayout(filterStore.level2Filter);
            }
            
            // Set up observers after initial layout
            nextTick(() => {
                setupResizeObserver();
                setupImageLoadListeners();
            });
        });
    });
});

// Handle locale changes - reset isReady to force clean remount of waterfall
watch(
    currentLocale,
    async (newLocale, oldLocale) => {
        if (newLocale !== oldLocale && oldLocale !== undefined) {
            // Hide waterfall during transition
            isReady.value = false;
            
            // Wait for DOM to update
            await nextTick();
            
            // Show waterfall again after DOM is ready
            await nextTick();
            isReady.value = true;
            
            // Setup observers after remount
            await nextTick();
            setupResizeObserver();
            setupImageLoadListeners();
        }
    },
    { immediate: false }
);

onBeforeUnmount(() => {
    if (resizeObserver) {
        resizeObserver.disconnect();
        resizeObserver = null;
    }
    if (layoutUpdateTimeout) {
        cancelAnimationFrame(layoutUpdateTimeout);
        layoutUpdateTimeout = null;
    }
    if (refreshLayoutTimeout) {
        cancelAnimationFrame(refreshLayoutTimeout);
        refreshLayoutTimeout = null;
    }
    cardHeights.clear();
});

function refreshLayout(filter) {
    // Cancel any pending refresh to debounce multiple rapid filter changes
    if (refreshLayoutTimeout) {
        cancelAnimationFrame(refreshLayoutTimeout);
    }
    
    // Use requestAnimationFrame to batch updates and prevent flicker
    refreshLayoutTimeout = requestAnimationFrame(() => {
        // A 1ms setTimeout after nextTick
        // This ensures the DOM is ready before updating the layout
        nextTick(() => {
            setTimeout(() => {
                if (waterfall.value && filteredTiles.value.length > 0) {
                    try {
                        waterfall.value.updateOrder();
                        
                        // Re-setup observers and show waterfall after layout is complete
                        nextTick(() => {
                            setupResizeObserver();
                            setupImageLoadListeners();
                            // Show waterfall after positioning is complete
                            isTransitioning.value = false;
                        });
                    } catch (error) {
                        logError('Error refreshing waterfall layout:', error);
                        isTransitioning.value = false;
                    }
                } else {
                    // No tiles to show, end transition
                    isTransitioning.value = false;
                }
            }, 1);
        });
    });
}
</script>

