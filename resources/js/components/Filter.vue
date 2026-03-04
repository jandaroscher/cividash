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
            v-if="showFilter && filterGroups.length > 0"
            class="flex flex-wrap mb-5 md:mb-10"
            role="tablist"
            aria-label="Filter navigation"
        >
            <button
                v-for="(group, index) in filterGroups"
                :key="group.id"
                :id="getTabId(group.id)"
                role="tab"
                :aria-selected="filterStore.level1Filter === group.key"
                :aria-controls="getPanelId(group.id)"
                :tabindex="filterStore.level1Filter === group.key ? 0 : -1"
                @click="changeLevel1Filter(group.key)"
                @keydown="handleTabKeydown($event, group.key, index)"
                :class="[
                    'filter-button',
                    { 'filter-button--active': filterStore.level1Filter === group.key }
                ]"
                :style="getButtonStyles(group.key)"
            >
                {{ getGroupTitle(group) }}
            </button>
        </div>

        <div v-if="showFilter && activeGroup" class="-mx-[30px] sm:mx-0">
            <div
                :id="getPanelId(activeGroup.id)"
                role="tabpanel"
                :aria-labelledby="getTabId(activeGroup.id)"
            >
                <FilterGroup :group="activeGroup" />
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
import { getApiBaseUrl } from '../utils/api';
import Tooltip from './help/Tooltip.vue';
import FilterGroup from './filter/FilterGroup.vue';

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

const searchQuery = ref(filterStore.searchQuery || '');
let searchTimeout = null;

const searchPlaceholder = computed(() => {
    return currentLocale.value === 'en' ? 'Search tiles...' : 'Kacheln durchsuchen...';
});

const filterGroups = computed(() => filterStore.groups || []);
const activeGroup = computed(() => {
    return filterGroups.value.find(group => group.key === filterStore.level1Filter) || filterGroups.value[0] || null;
});

const filterHeader = computed(() => {
    return filterLabels.value.header || 'Filter';
});

const filterLabels = ref({
    header: 'Filter',
});

const apiUrl = computed(() => getApiBaseUrl());

function handleSearchInput(event) {
    const value = event.target.value;
    searchQuery.value = value;

    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        filterStore.setSearchQuery(value);
    }, 300);
}

watch(
    () => filterStore.searchQuery,
    (newValue) => {
        if (newValue !== searchQuery.value) {
            searchQuery.value = newValue;
        }
    }
);

async function fetchFilterGroups(locale) {
    try {
        filterStore.setLoading(true);
        filterStore.setError(null);
        const res = await fetch(`${apiUrl.value}/api/filters?locale=${locale}`);

        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }

        const json = await res.json();
        const data = json.data || json;

        filterLabels.value = data.labels || { header: 'Filter' };
        filterStore.setGroups(data.groups || []);
        filterStore.restoreFromUrl();
    } catch (err) {
        logError('Error fetching filter groups:', err);
        filterLabels.value = { header: 'Filter' };
        filterStore.setGroups([]);
        filterStore.setError(err.message || 'Failed to load filters');
    } finally {
        filterStore.setLoading(false);
    }
}

onMounted(() => {
    fetchFilterGroups(currentLocale.value);
});

onBeforeUnmount(() => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
        searchTimeout = null;
    }
});

watch(currentLocale, (newLocale) => {
    fetchFilterGroups(newLocale);
});

function getGroupTitle(group) {
    if (!group) return '';
    if (typeof group.title === 'string') {
        return group.title;
    }
    if (typeof group.title === 'object' && group.title !== null) {
        return group.title[currentLocale.value] || group.title.de || group.title.en || '';
    }
    return '';
}

function getPanelId(id) {
    return `filter-panel-${id}`;
}

function getTabId(id) {
    return `filter-tab-${id}`;
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
            let targetIndex = currentIndex;
            if (key === 'ArrowLeft') {
                targetIndex = currentIndex > 0 ? currentIndex - 1 : filterGroups.value.length - 1;
            } else {
                targetIndex = currentIndex < filterGroups.value.length - 1 ? currentIndex + 1 : 0;
            }

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
    color: var(--accent-color, #E30613);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    padding: 0;
}

:deep(.flicking-arrow-prev:hover),
:deep(.flicking-arrow-next:hover) {
    box-shadow: 0px 1px 10px #707070;
}

:deep(.flicking-arrow-prev.flicking-arrow-disabled),
:deep(.flicking-arrow-next.flicking-arrow-disabled) {
    color: #e5e5e5;
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
