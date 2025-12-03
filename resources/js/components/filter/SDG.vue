<template>
    <div id="sdg-filter-container" class="relative">
        <div v-if="loading" class="text-center py-8 text-gray-600">
            {{ currentLocale.value === 'en' ? 'Loading SDG goals...' : 'Lade SDG-Ziele...' }}
        </div>
        <div v-else-if="error" class="text-center py-8 text-red-600">
            {{ currentLocale.value === 'en' ? 'Error loading SDG goals:' : 'Fehler beim Laden der SDG-Ziele:' }} {{ error }}
        </div>
        <div v-else-if="sdgZiele.length === 0" class="text-center py-8 text-gray-600">
            {{ currentLocale.value === 'en' ? 'No SDG goals available' : 'Keine SDG-Ziele verfügbar' }}
        </div>
        <Flicking
            v-else
            ref="sdgFlicking"
            class="pb-4"
            :plugins="plugins"
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
                v-for="sdg in sdgZiele"
                :key="sdg.id"
                :aria-label="getSDGTitle(sdg)"
                class="min-h-[204px] flex flex-col items-center justify-center cursor-pointer mr-10"
                @click="selectSDG(sdg)"
            >
                <img
                    v-if="getSDGIcon(sdg)"
                    class="mb-3 w-30 max-w-none transition-shadow duration-200"
                    :class="{ 
                        'shadow-filter': isSelected(sdg),
                        'hover:shadow-filter': !isSelected(sdg)
                    }"
                    :alt="getSDGTitle(sdg)"
                    :src="getSDGIcon(sdg)"
                    width="120"
                    height="120"
                    loading="lazy"
                />
            </button>

            <template #viewport>
                <div class="xl:hidden flicking-pagination"></div>
            </template>
        </Flicking>

        <button
            type="button"
            class="flicking-arrow-prev flicking-arrow-prev-sdg is-outside hidden xl:block"
            :aria-label="currentLocale === 'en' ? 'Previous SDG goals' : 'Vorherige SDG-Ziele'"
            @click="sdgFlicking?.prev()"
            @keydown.enter.prevent="sdgFlicking?.prev()"
            @keydown.space.prevent="sdgFlicking?.prev()"
        ></button>
        <button
            type="button"
            class="flicking-arrow-next flicking-arrow-next-sdg is-outside hidden xl:block"
            :aria-label="currentLocale === 'en' ? 'Next SDG goals' : 'Nächste SDG-Ziele'"
            @click="sdgFlicking?.next()"
            @keydown.enter.prevent="sdgFlicking?.next()"
            @keydown.space.prevent="sdgFlicking?.next()"
        ></button>
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

const sdgZiele = ref([]);
const loading = ref(false);
const error = ref(null);
const sdgFlicking = ref(null);
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024);
const panelsPerView = ref(7);
const circular = ref(false);
const align = ref('prev');
const plugins = ref([]);

// SSR-safe API URL computed
const apiUrl = computed(() => {
    if (typeof window !== 'undefined' && window.APP_URL) {
        return window.APP_URL;
    }
    return '';
});

function getSDGTitle(sdg) {
    if (typeof sdg.title === 'string') {
        return sdg.title;
    }
    if (typeof sdg.title === 'object' && sdg.title !== null) {
        return sdg.title[currentLocale.value] || sdg.title.de || sdg.title.en || '';
    }
    return '';
}

function getSDGIcon(sdg) {
    if (!sdg.icon) {
        return null;
    }
    // Icon is already a full URL from API (SDGZielResource returns full URL)
    if (typeof sdg.icon === 'string') {
        return sdg.icon;
    }
    if (typeof sdg.icon === 'object' && sdg.icon !== null) {
        const iconUrl = sdg.icon[currentLocale.value] || sdg.icon.de || sdg.icon.en || null;
        // If iconUrl is already a full URL, return it
        if (iconUrl && (iconUrl.startsWith('http://') || iconUrl.startsWith('https://'))) {
            return iconUrl;
        }
        // Otherwise construct full URL using SSR-safe apiUrl
        if (iconUrl) {
            return `${apiUrl.value}/storage/${iconUrl}`;
        }
        return null;
    }
    return null;
}

function getSDGKey(sdg) {
    // Use id as key (like reference app: field[0] is the id)
    return sdg.id?.toString();
}

function isSelected(sdg) {
    if (!filterStore.level2Filter?.key) {
        return false;
    }
    const sdgKey = getSDGKey(sdg);
    return sdgKey === filterStore.level2Filter.key;
}

function selectSDG(sdg) {
    const title = getSDGTitle(sdg);
    const key = getSDGKey(sdg);
    
    if (isSelected(sdg)) {
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
        panelsPerView.value = 7;
    } else if (windowWidth.value >= 768 && windowWidth.value < 1024) {
        panelsPerView.value = 5;
    } else if (windowWidth.value >= 640 && windowWidth.value < 768) {
        panelsPerView.value = 4;
    } else if (windowWidth.value >= 420 && windowWidth.value < 640) {
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

// Debounce function to throttle resize events
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

const debouncedResize = debounce(onResize, 150);

async function fetchSDGZiele() {
    loading.value = true;
    error.value = null;
    filterStore.setLoading('sdg', true);
    
    try {
        const locale = currentLocale.value;
        const res = await fetch(`${apiUrl.value}/api/sdg-ziele?locale=${locale}`);
        
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        
        const json = await res.json();
        const fetchedSDGZiele = json.data || [];
        
        sdgZiele.value = fetchedSDGZiele;
        filterStore.setSDGZiele(fetchedSDGZiele);
    } catch (err) {
        error.value = err.message || 'Failed to load SDG goals';
    } finally {
        loading.value = false;
        filterStore.setLoading('sdg', false);
    }
}

onMounted(() => {
    // Initialize plugins after component is mounted to avoid SSR issues
    plugins.value = [
        new Arrow({ parentEl: document.body, prevElSelector: '.flicking-arrow-prev-sdg', nextElSelector: '.flicking-arrow-next-sdg' }),
        new Pagination({ type: 'bullet' })
    ];
    
    setPanelsPerView();
    setCircularAndAlign();
    window.addEventListener('resize', debouncedResize);
    
    // Use cached data if available, otherwise fetch
    if (filterStore.sdgZiele.length > 0) {
        sdgZiele.value = filterStore.sdgZiele;
    } else {
        fetchSDGZiele();
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', debouncedResize);
});

// Refetch when locale changes
watch(currentLocale, () => {
    fetchSDGZiele();
});
</script>

