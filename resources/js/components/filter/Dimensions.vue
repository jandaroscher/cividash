<template>
    <div id="filter-container" class="relative">
        <div v-if="loading" class="text-center py-8 text-gray-600">
            {{ currentLocale.value === 'en' ? 'Loading dimensions...' : 'Lade Dimensionen...' }}
        </div>
        <div v-else-if="error" class="text-center py-8 text-red-600">
            {{ currentLocale.value === 'en' ? 'Error loading dimensions:' : 'Fehler beim Laden der Dimensionen:' }} {{ error }}
        </div>
        <div v-else-if="dimensions.length === 0" class="text-center py-8 text-gray-600">
            {{ currentLocale.value === 'en' ? 'No dimensions available' : 'Keine Dimensionen verfügbar' }}
        </div>
        <Flicking
            v-else
            ref="dimensionFlicking"
            class="pb-4"
            :plugins="plugins.value"
            :options="{
                align: 'center',
                defaultIndex: 0,
                circular: true,
                circularFallback: 'bound',
                moveType: 'snap',
                panelsPerView
            }"
        >
            <button
                v-for="dimension in dimensions"
                :key="dimension.id"
                :aria-label="getDimensionTitle(dimension)"
                class="min-h-[204px] flex flex-col items-center cursor-pointer mr-10 md:mr-20"
                @click="selectDimension(dimension)"
            >
                <span
                    :class="{ 'bg-gray-200/60 rounded-full': isSelected(dimension) }"
                    class="block rounded-full hover:bg-gray-200/60 mb-3 p-2 transition-colors duration-200"
                >
                    <img
                        v-if="getDimensionIcon(dimension)"
                        class="w-30 max-w-none"
                        :alt="getDimensionTitle(dimension)"
                        :src="getDimensionIcon(dimension)"
                        width="120"
                        height="120"
                        loading="lazy"
                    />
                </span>
                <span class="block text-xl text-center">{{ getDimensionTitle(dimension) }}</span>
            </button>

            <template #viewport>
                <div class="sm:hidden flicking-pagination"></div>
            </template>
        </Flicking>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import Flicking from '@egjs/vue3-flicking';
import '@egjs/vue3-flicking/dist/flicking.css';
import { Pagination } from '@egjs/flicking-plugins';
import '@egjs/flicking-plugins/dist/pagination.css';
import { useFilterStore } from '../../stores/filter';
import { useLocale } from '../../composables/useLocale';

const filterStore = useFilterStore();
const { currentLocale } = useLocale();

const dimensions = ref([]);
const loading = ref(false);
const error = ref(null);
const dimensionFlicking = ref(null);
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024);
const panelsPerView = ref(5);
const plugins = ref([]);

function getDimensionTitle(dimension) {
    if (typeof dimension.title === 'string') {
        return dimension.title;
    }
    if (typeof dimension.title === 'object' && dimension.title !== null) {
        return dimension.title[currentLocale.value] || dimension.title.de || dimension.title.en || '';
    }
    return '';
}

function getDimensionIcon(dimension) {
    if (!dimension.icon) {
        return null;
    }
    // Icon is already a full URL from API (HandlungsdimensionResource returns full URL)
    return dimension.icon;
}

function isSelected(dimension) {
    if (!filterStore.level2Filter?.key) {
        return false;
    }
    // Match by key (primary) or id (fallback)
    return dimension.key === filterStore.level2Filter.key;
}

function selectDimension(dimension) {
    const title = getDimensionTitle(dimension);
    // Use key as primary identifier (like reference app)
    // Fallback to id if key is not available
    const key = dimension.key || dimension.id?.toString();
    
    if (!key) {
        console.error('Dimension has no key or id:', dimension);
        return;
    }
    
    if (isSelected(dimension)) {
        // Deselect if already selected
        filterStore.clearFilters();
    } else {
        filterStore.setLevel2Filter({
            key: key,
            title: title,
        });
    }
}

function setPanelsPerView() {
    if (windowWidth.value >= 768) {
        panelsPerView.value = 5;
    } else if (windowWidth.value >= 640) {
        panelsPerView.value = 3;
    } else {
        panelsPerView.value = 2;
    }
}

function onResize() {
    windowWidth.value = window.innerWidth;
    setPanelsPerView();
}

async function fetchDimensions() {
    loading.value = true;
    error.value = null;
    filterStore.setLoading('dimensions', true);
    
    try {
        const apiUrl = window.APP_URL || '';
        const locale = currentLocale.value;
        const res = await fetch(`${apiUrl}/api/handlungsdimensionen?locale=${locale}`);
        
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        
        const json = await res.json();
        const fetchedDimensions = json.data || [];
        
        dimensions.value = fetchedDimensions;
        filterStore.setDimensions(fetchedDimensions);
    } catch (err) {
        error.value = err.message || 'Failed to load dimensions';
        console.error('Error fetching dimensions:', err);
    } finally {
        loading.value = false;
        filterStore.setLoading('dimensions', false);
    }
}

onMounted(() => {
    // Initialize plugins after component is mounted to avoid SSR issues
    plugins.value = [
        new Pagination({ type: 'bullet' })
    ];
    
    setPanelsPerView();
    window.addEventListener('resize', onResize);
    
    // Use cached data if available, otherwise fetch
    if (filterStore.dimensions.length > 0) {
        dimensions.value = filterStore.dimensions;
    } else {
        fetchDimensions();
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
});

// Refetch when locale changes
watch(currentLocale, () => {
    fetchDimensions();
});
</script>

