<template>
    <div class="container pt-6 md:mb-10 px-3 sm:px-[30px] overflow-hidden">
        <VueFlexWaterfall
            v-if="filteredTiles.length > 0"
            ref="waterfall"
            class="h-full max-w-[363px] mx-auto md:max-w-none md:mx-0"
            align-content="center"
            :col="colCount"
            col-spacing="40"
            :break-at="{ 1280: mdColCount, 825: 1 }"
        >
            <TileCard
                v-for="tile in filteredTiles"
                :key="tile.id"
                :tile="tile"
            />
        </VueFlexWaterfall>
        <div v-if="visibleTilesCount === 0" class="text-center py-10 text-gray-600">
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
import TileCard from './TileCard.vue';
import { useTilesStore } from '../stores/tiles';
import { useFilterStore } from '../stores/filter';
import { useLocale } from '../composables/useLocale';

const tilesStore = useTilesStore();
const filterStore = useFilterStore();
const { currentLocale } = useLocale();

const waterfall = ref(null);
const colCount = ref(3);
const mdColCount = ref(2);
let resizeObserver = null;
let layoutUpdateTimeout = null;
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
    // First check search query
    if (!matchesSearch(tile, filterStore.searchQuery)) {
        return false;
    }
    
    // Then check filter
    if (!filterStore.level2Filter?.key) {
        return true;
    }

    const filterType = filterStore.level1Filter; // 'dimensions' | 'fields' | 'sdg' | null
    const filterKey = filterStore.level2Filter.key;

    if (filterType === 'dimensions') {
        const dimension = tile.handlungsdimension;
        if (!dimension) {
            return false;
        }
        const dimensionKey = typeof dimension === 'string' 
            ? dimension 
            : (dimension.key || dimension.id?.toString() || null);
        
        if (!dimensionKey) {
            return false;
        }
        
        return String(dimensionKey) === String(filterKey);
    }
    
    if (filterType === 'fields' || !filterType) {
        const categories = tile.handlungsfelder || tile.categories || [];
        return categories.some(cat => {
            return cat.id?.toString() === filterKey;
        });
    }
    
    if (filterType === 'sdg') {
        const sdgZiele = tile.sdg_ziele || [];
        return sdgZiele.some(sdg => {
            return sdg.id?.toString() === filterKey;
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
        refreshLayout(filter);
    },
    { deep: true }
);

watch(
    () => filterStore.searchQuery,
    () => {
        refreshLayout(filterStore.level2Filter);
    }
);

watch(
    filteredTiles,
    () => {
        refreshLayout(filterStore.level2Filter);
    }
);

function setupResizeObserver() {
    if (!waterfall.value?.$el) {
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
    const tileCards = waterfall.value.$el.querySelectorAll('.shadow-card');
    tileCards.forEach((card) => {
        // Store initial height
        cardHeights.set(card, card.offsetHeight);
        resizeObserver.observe(card);
    });
}

function setupImageLoadListeners() {
    if (!waterfall.value?.$el) {
        return;
    }
    
    // Listen for image load events that might change tile heights
    const images = waterfall.value.$el.querySelectorAll('img[loading="lazy"]');
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
    // Wait for tiles to be loaded and DOM to be ready
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

onBeforeUnmount(() => {
    if (resizeObserver) {
        resizeObserver.disconnect();
        resizeObserver = null;
    }
    if (layoutUpdateTimeout) {
        cancelAnimationFrame(layoutUpdateTimeout);
        layoutUpdateTimeout = null;
    }
    cardHeights.clear();
});

function refreshLayout(filter) {
    const cardCount = visibleTilesCount.value;

    if (cardCount === 1) {
        colCount.value = 1;
        mdColCount.value = 1;
    } else if (cardCount === 2) {
        colCount.value = 2;
        mdColCount.value = 2;
    } else {
        colCount.value = 3;
        mdColCount.value = 2;
    }

    // Use setTimeout with 1ms delay like the reference app
    // This ensures the DOM is ready before updating the layout
    nextTick(() => {
        setTimeout(() => {
            if (waterfall.value && filteredTiles.value.length > 0) {
                try {
                    waterfall.value.updateOrder();
                    
                    // Re-setup observers after layout refresh
                    nextTick(() => {
                        setupResizeObserver();
                        setupImageLoadListeners();
                    });
                } catch (error) {
                    console.error('Error refreshing waterfall layout:', error);
                }
            }
        }, 1);
    });
}
</script>

