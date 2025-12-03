<template>
    <div class="container">
        <div class="flex flex-col xl:flex-row">
            <h2 class="text-3xl text-black font-bold mb-6 lg:mb-12 hyphens-auto order-2 xl:order-1">
                {{ filterHeader }}
            </h2>
        </div>
        <div class="flex flex-wrap mb-15">
            <button
                v-for="filter in level1Filters"
                :key="filter"
                @click="changeLevel1Filter(filter)"
                :class="[
                    'filter-button',
                    { 'filter-button--active': filterStore.level1Filter === filter }
                ]"
                :style="getButtonStyles(filter)"
            >
                {{ getFilterLabel(filter) }}
            </button>
        </div>

        <div class="-mx-[30px] sm:mx-0">
            <Dimensions v-if="filterStore.level1Filter === 'dimensions'" />
            <Fields v-if="filterStore.level1Filter === 'fields'" />
            <SDG v-if="filterStore.level1Filter === 'sdg'" />
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted, watch } from 'vue';
import { useFilterStore } from '../stores/filter';
import { useBrandingStore } from '../stores/branding';
import { useLocale } from '../composables/useLocale';
import Dimensions from './filter/Dimensions.vue';
import Fields from './filter/Fields.vue';
import SDG from './filter/SDG.vue';

const filterStore = useFilterStore();
const brandingStore = useBrandingStore();
const { currentLocale } = useLocale();

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

watch(currentLocale, (newLocale) => {
    fetchFilterLabels(newLocale);
});

const filterHeader = computed(() => {
    return filterLabels.value.header || 'Filter';
});

function getFilterLabel(filter) {
    return filterLabels.value[filter] || filter;
}

function changeLevel1Filter(filter) {
    filterStore.setLevel1Filter(filter);
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
    transition: background-color 0.2s, border-color 0.2s, color 0.2s;
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

