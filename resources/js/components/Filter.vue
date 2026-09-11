<template>
  <div class="container">
    <div
      v-if="effectiveHeading || showSearch"
      class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4 mb-5 md:mb-10 xl:mb-12"
    >
      <h2
        v-if="effectiveHeading"
        class="content-heading text-theme-h3 hyphens-auto order-1 xl:order-1 mb-0"
      >
        {{ effectiveHeading }}
      </h2>

      <!-- Search Input -->
      <div
        v-if="showSearch"
        class="order-2 xl:order-2 xl:shrink-0 xl:w-[508px]"
      >
        <Tooltip
          :text="getSearchTooltip()"
          position="top"
          wrapper-class="w-full"
          trigger-class="w-full"
        >
          <div
            class="search-field flex items-center gap-[18px] border pl-[30px] pr-[20px] py-[16px] focus-within:ring-2 focus-within:ring-offset-0"
            :style="{ '--tw-ring-color': brandingStore.primaryColor, borderColor: '#9B9B9B' }"
          >
            <svg
              class="shrink-0"
              width="22"
              height="22"
              viewBox="0 0 22 22"
              fill="none"
              aria-hidden="true"
            >
              <path
                d="M9.68848 0C15.0306 0.00012322 19.377 4.3466 19.377 9.68848C19.3769 12.0384 18.5345 14.1948 17.1377 15.874L21.7383 20.4736C22.0873 20.8227 22.0874 21.3893 21.7383 21.7383C21.5636 21.9129 21.3341 22 21.1055 22C20.8767 22 20.6482 21.9127 20.4736 21.7383L15.873 17.1377C14.1939 18.5342 12.0382 19.3769 9.68848 19.377C4.3466 19.377 0.000123217 15.0306 0 9.68848C0 4.34652 4.34652 0 9.68848 0ZM9.68848 1.78906C5.33264 1.78906 1.78906 5.33264 1.78906 9.68848C1.78916 14.0442 5.33271 17.5879 9.68848 17.5879C14.0441 17.5878 17.5878 14.0441 17.5879 9.68848C17.5879 5.33272 14.0442 1.78919 9.68848 1.78906Z"
                :fill="brandingStore.primaryColor"
              />
            </svg>
            <input
              ref="searchInputRef"
              v-model="searchQuery"
              type="text"
              :placeholder="searchPlaceholder"
              class="flex-1 min-w-0 border-0 bg-transparent focus:outline-none focus:ring-0 p-0"
              :style="{ color: '#191919', fontSize: '1.25rem', lineHeight: 'normal' }"
              :aria-label="currentLocale === 'en' ? 'Search tiles' : 'Kacheln durchsuchen'"
              @input="handleSearchInput"
            >
            <button
              type="button"
              class="search-clear-button shrink-0"
              :class="searchQuery ? 'is-visible' : ''"
              :tabindex="searchQuery ? 0 : -1"
              :aria-hidden="!searchQuery"
              :aria-label="currentLocale === 'en' ? 'Clear search' : 'Suche leeren'"
              :style="{ '--focus-ring-color': brandingStore.primaryColor }"
              @click="clearSearch"
            >
              <svg
                width="16"
                height="16"
                viewBox="0 0 16 16"
                fill="none"
                aria-hidden="true"
              >
                <path
                  d="M1 1L15 15M15 1L1 15"
                  stroke="#9B9B9B"
                  stroke-width="2"
                  stroke-linecap="round"
                />
              </svg>
            </button>
          </div>
        </Tooltip>
      </div>
    </div>

    <div
      v-if="showFilter && filterGroups.length > 0"
      class="filter-tabs mb-5 md:mb-10"
      role="tablist"
      :aria-label="currentLocale === 'en' ? 'Filter navigation' : 'Filter-Navigation'"
    >
      <button
        v-for="(group, index) in filterGroups"
        :id="getTabId(group.id)"
        :key="group.id"
        role="tab"
        :aria-selected="filterStore.level1Filter === group.key"
        :aria-controls="getPanelId(group.id)"
        :tabindex="filterStore.level1Filter === group.key ? 0 : -1"
        :class="[
          'filter-button',
          { 'filter-button--active': filterStore.level1Filter === group.key }
        ]"
        :style="getButtonStyles()"
        @click="changeLevel1Filter(group.key)"
        @keydown="handleTabKeydown($event, group.key, index)"
      >
        <span class="filter-button__fill">{{ getGroupTitle(group) }}</span>
      </button>
    </div>

    <div
      v-if="showFilter && activeGroup"
      class="-mx-[30px] sm:mx-0"
    >
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
import { logError } from '../lib/log.js';
import { computed, ref, onMounted, onBeforeUnmount, watch } from 'vue';
import { useFilterStore } from '../stores/filter';
import { useBrandingStore } from '../stores/branding';
import { useLocale } from '../composables/useLocale';
import { useHelpContext } from '../composables/useHelpContext';
import { getApiBaseUrl } from '../utils/api';
import { hexToRgba } from '../utils/color';
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
    heading: {
        type: String,
        default: null,
    },
});

const filterStore = useFilterStore();
const brandingStore = useBrandingStore();
const { currentLocale } = useLocale();
const { getTooltip } = useHelpContext();

const searchQuery = ref(filterStore.searchQuery || '');
const searchInputRef = ref(null);
let searchTimeout = null;

const searchPlaceholder = computed(() => t('searchPlaceholder', currentLocale.value));

const filterGroups = computed(() => filterStore.groups || []);
const activeGroup = computed(() => {
    return filterGroups.value.find(group => group.key === filterStore.level1Filter) || filterGroups.value[0] || null;
});

const effectiveHeading = computed(() => {
    return props.heading || null;
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

function clearSearch() {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
        searchTimeout = null;
    }
    searchQuery.value = '';
    filterStore.setSearchQuery('');
    // Return focus to the input: the clear button hides once the field is
    // empty, so without this the focus would vanish along with it.
    searchInputRef.value?.focus();
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

        filterStore.setGroups(data.groups || []);
        filterStore.restoreFromUrl();
    } catch (err) {
        logError('Error fetching filter groups:', err);
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
        case 'ArrowRight': {
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
        }
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

function getButtonStyles() {
    // Active fill uses the full tenant primary color; hover uses a soft tint
    // of the same color so the hover state reads as a preview, not a
    // selection (active !== hover).
    return {
        '--active-fill': brandingStore.primaryColor,
        '--hover-fill': hexToRgba(brandingStore.primaryColor, 0.12),
        '--focus-ring-color': brandingStore.primaryColor,
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
    opacity: 0.4;
    cursor: default;
    box-shadow: 0px 3px 6px #00000029;
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

/* v05: every group is its own grey surface in a column grid, not one
   continuous bar - wrapped rows stay aligned to the grid instead of
   trailing behind a shared background. */
.filter-tabs {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

@media (min-width: 768px) {
    .filter-tabs {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

.filter-button {
    box-sizing: border-box;
    display: flex;
    border: none;
    background: transparent;
    cursor: pointer;
}

.filter-button__fill {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 50px;
    font-size: 1.25rem;
    /* Tight leading and vertical padding keep two-line labels centred on
       narrow screens. */
    line-height: 1.3;
    text-align: center;
    padding: 10px 8px;
    /* Long group names ("Handlungsdimensionen") must not widen the
       column on mobile; hyphenate like the design reference. */
    hyphens: auto;
    overflow-wrap: anywhere;
    color: #191919;
    background-color: #F0F0F0;
    transition: background-color 0.2s, color 0.2s;
}

@media (min-width: 768px) {
    .filter-button__fill {
        min-height: 52px;
        line-height: 2rem;
        padding: 0 8px;
    }
}

.filter-button--active .filter-button__fill {
    background-color: var(--active-fill);
    color: white;
}

.filter-button:not(.filter-button--active):hover .filter-button__fill {
    background-color: var(--hover-fill);
}

.filter-button--active:hover .filter-button__fill {
    background-color: var(--active-fill);
}

.filter-button:focus-visible {
    outline: 2px solid var(--focus-ring-color, #191919);
    outline-offset: 2px;
}

/* Search field */
.search-field input::placeholder {
    color: rgba(25, 25, 25, 0.5);
}

.search-clear-button {
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.15s;
}

.search-clear-button.is-visible {
    opacity: 1;
    pointer-events: auto;
}

.search-clear-button:hover svg path,
.search-clear-button:focus-visible svg path {
    stroke: #191919;
}

.search-clear-button:focus-visible {
    outline: 2px solid var(--focus-ring-color, #191919);
    outline-offset: 2px;
    border-radius: 2px;
}
</style>
