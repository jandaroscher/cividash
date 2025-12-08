<template>
    <div class="container">
        <div class="flex flex-col xl:flex-row">
            <h2 class="text-3xl text-black font-bold mb-6 lg:mb-12 hyphens-auto order-2 xl:order-1">
                {{ filterHeader }}
            </h2>
        </div>

        <!-- Search Input -->
        <div v-if="showSearch" class="mb-5 md:mb-10">
            <Tooltip
                :text="getSearchTooltip()"
                position="top"
                wrapper-class="w-full"
                trigger-class="w-full"
            >
                <input
                    v-model="searchQuery"
                    @input="handleSearchInput"
                    type="text"
                    :placeholder="searchPlaceholder"
                    class="w-full px-4 border focus:outline-none focus:ring-2 focus:ring-offset-0"
                    :style="{ 
                        '--tw-ring-color': brandingStore.primaryColor,
                        borderColor: '#191919',
                        height: '4rem',
                        fontSize: '1.25rem',
                        lineHeight: '2rem'
                    }"
                    aria-label="Search tiles"
                />
            </Tooltip>
        </div>

        <div 
            v-if="showFilter"
            class="flex flex-wrap mb-5 md:mb-10" 
            role="tablist"
            aria-label="Filter navigation"
        >
            <button
                v-for="(filter, index) in level1Filters"
                :key="filter"
                :id="getTabId(filter)"
                role="tab"
                :aria-selected="filterStore.level1Filter === filter"
                :aria-controls="getPanelId(filter)"
                :tabindex="filterStore.level1Filter === filter ? 0 : -1"
                @click="changeLevel1Filter(filter)"
                @keydown="handleTabKeydown($event, filter, index)"
                :class="[
                    'filter-button',
                    { 'filter-button--active': filterStore.level1Filter === filter }
                ]"
                :style="getButtonStyles(filter)"
            >
                {{ getFilterLabel(filter) }}
            </button>
        </div>

        <div v-if="showFilter" class="-mx-[30px] sm:mx-0">
            <div
                v-if="filterStore.level1Filter === 'dimensions'"
                id="dimensions-panel"
                role="tabpanel"
                :aria-labelledby="getTabId('dimensions')"
            >
                <Dimensions />
            </div>
            <div
                v-if="filterStore.level1Filter === 'fields'"
                id="fields-panel"
                role="tabpanel"
                :aria-labelledby="getTabId('fields')"
            >
                <Fields />
            </div>
            <div
                v-if="filterStore.level1Filter === 'sdg'"
                id="sdg-panel"
                role="tabpanel"
                :aria-labelledby="getTabId('sdg')"
            >
                <SDG />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount, watch } from 'vue';
import { useFilterStore } from '../stores/filter';
import { useBrandingStore } from '../stores/branding';
import { useLocale } from '../composables/useLocale';
import { useHelpContext } from '../composables/useHelpContext';
import Tooltip from './help/Tooltip.vue';
import Dimensions from './filter/Dimensions.vue';
import Fields from './filter/Fields.vue';
import SDG from './filter/SDG.vue';

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

const filterStore = useFilterStore();
const brandingStore = useBrandingStore();
const { currentLocale } = useLocale();
const { getTooltip } = useHelpContext();

// Search input with debouncing
const searchQuery = ref(filterStore.searchQuery || '');
let searchTimeout = null;

const searchPlaceholder = computed(() => {
    return currentLocale.value === 'en' ? 'Search tiles...' : 'Kacheln durchsuchen...';
});

function handleSearchInput(event) {
    const value = event.target.value;
    searchQuery.value = value;
    
    // Debounce search updates (300ms)
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    
    searchTimeout = setTimeout(() => {
        filterStore.setSearchQuery(value);
    }, 300);
}

// Sync searchQuery with store when restored from URL
watch(
    () => filterStore.searchQuery,
    (newValue) => {
        if (newValue !== searchQuery.value) {
            searchQuery.value = newValue;
        }
    }
);

const level1Filters = ['dimensions', 'fields', 'sdg'];

// Filter labels from API
const filterLabels = ref({
    dimensions: 'Handlungsdimensionen',
    fields: 'Handlungsfelder',
    sdg: 'SDG-Ziele',
    header: 'Filter',
});

// SSR-safe API URL
const apiUrl = computed(() => {
    if (typeof window !== 'undefined' && window.APP_URL) {
        return window.APP_URL;
    }
    return '';
});

async function fetchFilterLabels(locale) {
    try {
        const res = await fetch(`${apiUrl.value}/api/filters?locale=${locale}`);
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        const json = await res.json();
        // Extract labels from response (json.data.labels)
        filterLabels.value = json.data?.labels || json.labels || {
            dimensions: 'Handlungsdimensionen',
            fields: 'Handlungsfelder',
            sdg: 'SDG-Ziele',
            header: 'Filter',
        };
    } catch (err) {
        console.error('Error fetching filter labels:', err);
        // Fallback to German labels on error
        filterLabels.value = {
            dimensions: 'Handlungsdimensionen',
            fields: 'Handlungsfelder',
            sdg: 'SDG-Ziele',
            header: 'Filter',
        };
    }
}

// Fetch labels on mount and when locale changes
onMounted(() => {
    fetchFilterLabels(currentLocale.value);
});

// Cleanup debounce timer on component unmount
onBeforeUnmount(() => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
        searchTimeout = null;
    }
});

watch(currentLocale, (newLocale) => {
    fetchFilterLabels(newLocale);
});

const filterHeader = computed(() => {
    return filterLabels.value.header || 'Filter';
});

function getFilterLabel(filter) {
    return filterLabels.value[filter] || filter;
}

function getPanelId(filter) {
    return `${filter}-panel`;
}

function getTabId(filter) {
    return `${filter}-tab`;
}

function changeLevel1Filter(filter) {
    filterStore.setLevel1Filter(filter);
}

function handleTabKeydown(event, filter, currentIndex) {
    const { key } = event;

    switch (key) {
        case 'ArrowLeft':
        case 'ArrowRight':
            event.preventDefault();
            // Calculate target index with wrap-around
            let targetIndex = currentIndex;
            if (key === 'ArrowLeft') {
                targetIndex = currentIndex > 0 ? currentIndex - 1 : level1Filters.length - 1;
            } else {
                targetIndex = currentIndex < level1Filters.length - 1 ? currentIndex + 1 : 0;
            }
            
            // Only move focus, do not activate the tab
            setTimeout(() => {
                const tablist = event.target.closest('[role="tablist"]');
                if (tablist) {
                    const buttons = tablist.querySelectorAll('[role="tab"]');
                    if (buttons[targetIndex]) {
                        buttons[targetIndex].focus();
                    }
                }
            }, 0);
            break;
        case 'Enter':
        case ' ':
            event.preventDefault();
            changeLevel1Filter(filter);
            break;
        default:
            return;
    }
}

function getSearchTooltip() {
    return getTooltip('searchInput') || (currentLocale.value === 'en' ? 'Search tiles by title or description' : 'Durchsuchen Sie Kacheln nach Titel oder Beschreibung');
}

function getButtonStyles(filter) {
    const isActive = filterStore.level1Filter === filter;
    return {
        '--hover-bg': brandingStore.primaryColor,
        '--hover-border': brandingStore.primaryColor,
        backgroundColor: isActive ? brandingStore.primaryColor : 'transparent',
        borderColor: isActive ? brandingStore.primaryColor : '#191919',
        color: isActive ? 'white' : '#191919',
    };
}

</script>

<style scoped>
/* Flicking camera - remove height: 100% to match reference app */
:deep(.flicking-camera) {
    height: auto !important;
}

/* Flicking arrow styles - matching reference app */
:deep(.flicking-arrow-prev),
:deep(.flicking-arrow-next) {
    top: calc(50% - 18px);
    height: 36px;
    width: 36px;
    border-radius: 50%;
    box-shadow: 0px 3px 6px #00000029;
    transition: box-shadow 0.2s;
}

:deep(.flicking-arrow-prev:hover),
:deep(.flicking-arrow-next:hover) {
    box-shadow: 0px 1px 10px #707070;
}

:deep(.flicking-arrow-prev) {
    background-image: url('/assets/images/arrow-left.svg');
}

:deep(.flicking-arrow-next) {
    background-image: url('/assets/images/arrow-right.svg');
}

:deep(.flicking-arrow-prev.flicking-arrow-disabled) {
    background-image: url('/assets/images/arrow-left-inactive.svg');
}

:deep(.flicking-arrow-next.flicking-arrow-disabled) {
    background-image: url('/assets/images/arrow-right-inactive.svg');
}

:deep(.flicking-arrow-disabled:hover) {
    box-shadow: 0px 3px 6px #00000029;
}

:deep(.flicking-arrow-prev::before),
:deep(.flicking-arrow-prev::after),
:deep(.flicking-arrow-next::before),
:deep(.flicking-arrow-next::after) {
    display: none;
}

:deep(.flicking-pagination-bullet-active) {
    background-color: var(--primary-color, #E30613) !important;
}

:deep(.flicking-pagination-scroll) {
    width: auto !important;
}

:deep(.shadow-filter) {
    box-shadow: 0px 3px 6px #00000029;
}

/* SDG filter hover and active states - more prominent */
:deep(#sdg-filter-container img.shadow-filter) {
    box-shadow: 0px 3px 6px #00000029 !important;
}

:deep(#sdg-filter-container img:hover) {
    box-shadow: 0px 3px 6px #00000029;
}

.filter-button {
    flex: 1 1 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 4rem;
    font-size: 1.25rem;
    line-height: 2rem;
    border: 1px solid;
    border-right: none;
    transition: background-color 0.2s, border-color 0.2s, color 0.2s;
}

.filter-button:last-child {
    border-right-width: 1px;
    border-right-style: solid;
}

@media (min-width: 768px) {
    .filter-button {
        flex: 1 1 calc(33.333% - 0.5rem);
    }
}

.filter-button:not(.filter-button--active):hover {
    background-color: var(--hover-bg) !important;
    border-color: var(--hover-border) !important;
    color: white !important;
}
</style>

