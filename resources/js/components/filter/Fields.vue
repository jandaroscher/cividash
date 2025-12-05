<template>
    <div id="field-filter-container" class="relative">
        <div v-if="loading" class="text-center py-8 text-gray-600">
            {{ currentLocale.value === 'en' ? 'Loading fields...' : 'Lade Felder...' }}
        </div>
        <div v-else-if="error" class="text-center py-8 text-red-600">
            {{ currentLocale.value === 'en' ? 'Error loading fields:' : 'Fehler beim Laden der Felder:' }} {{ error }}
        </div>
        <div v-else-if="fields.length === 0" class="text-center py-8 text-gray-600">
            {{ currentLocale.value === 'en' ? 'No fields available' : 'Keine Felder verfügbar' }}
        </div>
        <Flicking
            v-else
            ref="fieldsFlicking"
            class="pb-4"
            :plugins="plugins.value"
            :options="{
                align,
                defaultIndex: 0,
                circular,
                circularFallback: 'bound',
                moveType: 'snap',
                panelsPerView,
                bound: true
            }"
        >
            <button
                v-for="field in fields"
                :key="field.id"
                :aria-label="getFieldTitle(field)"
                class="min-h-[204px] flex flex-col items-center cursor-pointer mr-10 md:mr-18"
                @click="selectField(field)"
            >
                <span
                    :class="{ 'bg-gray-200/60 rounded-full': isSelected(field) }"
                    class="block rounded-full hover:bg-gray-200/60 mb-3 p-2 transition-colors duration-200"
                >
                    <img
                        v-if="field.icon"
                        class="w-30 max-w-none"
                        :alt="getFieldTitle(field)"
                        :src="field.icon"
                        width="120"
                        height="120"
                        loading="lazy"
                    />
                </span>
                <span class="block text-xl text-center">
                    {{ getFieldTitle(field) }}
                </span>
            </button>

            <template #viewport>
                <div class="xl:hidden flicking-pagination"></div>
            </template>
        </Flicking>

        <span
            class="flicking-arrow-prev flicking-arrow-prev-fields is-outside cursor-pointer"
            role="button"
            tabindex="0"
            :aria-label="currentLocale === 'en' ? 'Previous fields' : 'Vorherige Felder'"
            @keydown.enter.prevent="$refs.fieldsFlicking?.prev()"
            @keydown.space.prevent="$refs.fieldsFlicking?.prev()"
        ></span>
        <span
            class="flicking-arrow-next flicking-arrow-next-fields is-outside cursor-pointer"
            role="button"
            tabindex="0"
            :aria-label="currentLocale === 'en' ? 'Next fields' : 'Nächste Felder'"
            @keydown.enter.prevent="$refs.fieldsFlicking?.next()"
            @keydown.space.prevent="$refs.fieldsFlicking?.next()"
        ></span>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import Flicking from '@egjs/vue3-flicking';
import '@egjs/vue3-flicking/dist/flicking.css';
import { Arrow, Pagination } from '@egjs/flicking-plugins';
import '@egjs/flicking-plugins/dist/arrow.css';
import '@egjs/flicking-plugins/dist/pagination.css';
import { useFilterStore } from '../../stores/filter';
import { useLocale } from '../../composables/useLocale';

const filterStore = useFilterStore();
const { currentLocale } = useLocale();

const fields = ref([]);
const loading = ref(false);
const error = ref(null);
const fieldsFlicking = ref(null);
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024);
const panelsPerView = ref(5);
const circular = ref(false);
const align = ref('prev');
const plugins = ref([]);

function getFieldTitle(field) {
    // Fields use slug as title
    if (typeof field.slug === 'string') {
        return field.slug;
    }
    if (typeof field.slug === 'object' && field.slug !== null) {
        return field.slug[currentLocale.value] || field.slug.de || field.slug.en || '';
    }
    return '';
}

function getFieldKey(field) {
    // Use id as key (like reference app: field[0] is the id)
    // This matches the filter logic in Cards.vue which filters by id
    return field.id?.toString();
}

function isSelected(field) {
    if (!filterStore.level2Filter?.key) {
        return false;
    }
    const fieldKey = getFieldKey(field);
    return fieldKey === filterStore.level2Filter.key;
}

function selectField(field) {
    const title = getFieldTitle(field);
    const key = getFieldKey(field);
    
    if (isSelected(field)) {
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
    if (windowWidth.value >= 1024) {
        panelsPerView.value = 5;
    } else if (windowWidth.value >= 640 && windowWidth.value < 1024) {
        panelsPerView.value = 3;
    } else {
        panelsPerView.value = 2;
    }
}

function setCircularAndAlign() {
    if (windowWidth.value >= 640) {
        circular.value = false;
        align.value = 'prev';
    } else {
        circular.value = true;
        align.value = 'center';
    }
}

function onResize() {
    windowWidth.value = window.innerWidth;
    setPanelsPerView();
    setCircularAndAlign();
}

async function fetchFields() {
    loading.value = true;
    error.value = null;
    filterStore.setLoading('fields', true);
    
    try {
        const apiUrl = window.APP_URL || '';
        const locale = currentLocale.value;
        const res = await fetch(`${apiUrl}/api/handlungsfelder?locale=${locale}`);
        
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        
        const json = await res.json();
        const fetchedFields = json.data || [];
        
        fields.value = fetchedFields;
        filterStore.setFields(fetchedFields);
    } catch (err) {
        error.value = err.message || 'Failed to load fields';
        console.error('Error fetching fields:', err);
    } finally {
        loading.value = false;
        filterStore.setLoading('fields', false);
    }
}

onMounted(() => {
    // Initialize plugins after component is mounted to avoid SSR issues
    plugins.value = [
        new Arrow({ parentEl: document.body, prevElSelector: '.flicking-arrow-prev-fields', nextElSelector: '.flicking-arrow-next-fields' }),
        new Pagination({ type: 'bullet' })
    ];
    
    setPanelsPerView();
    setCircularAndAlign();
    window.addEventListener('resize', onResize);
    
    // Use cached data if available, otherwise fetch
    if (filterStore.fields.length > 0) {
        fields.value = filterStore.fields;
    } else {
        fetchFields();
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
});

// Refetch when locale changes
watch(currentLocale, () => {
    fetchFields();
});
</script>

