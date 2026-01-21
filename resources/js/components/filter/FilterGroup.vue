<template>
    <div ref="containerRef" class="relative">
        <div v-if="items.length === 0" class="text-center py-8 text-gray-600">
            {{ localeValue === 'en' ? 'No filters available' : 'Keine Filter verfügbar' }}
        </div>
        <Flicking
            v-else-if="isReady"
            :key="flickingKey"
            ref="flicking"
            class="pb-4"
            :plugins="activePlugins"
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
                v-for="item in items"
                :key="item.id"
                :aria-label="getItemTitle(item)"
                class="min-h-[204px] flex flex-col items-center cursor-pointer mr-10 md:mr-18"
                @click="selectItem(item)"
            >
                <span
                    :class="{ 'bg-gray-200/60 rounded-full': isSelected(item) }"
                    class="block rounded-full hover:bg-gray-200/60 mb-3 p-2 transition-colors duration-200"
                >
                    <img
                        v-if="getItemIcon(item)"
                        class="w-30 max-w-none"
                        :alt="getItemTitle(item)"
                        :src="getItemIcon(item)"
                        width="120"
                        height="120"
                        loading="lazy"
                    />
                </span>
                <span class="block text-xl text-center">
                    {{ getItemTitle(item) }}
                </span>
            </button>

            <template #viewport>
                <div class="xl:hidden flicking-pagination"></div>
            </template>
        </Flicking>

        <span
            ref="prevArrowRef"
            class="flicking-arrow-prev is-outside cursor-pointer"
            role="button"
            tabindex="0"
            :aria-label="localeValue === 'en' ? 'Previous filters' : 'Vorherige Filter'"
            @click="handlePrev"
            @keydown.enter.prevent="handlePrev"
            @keydown.space.prevent="handlePrev"
        ></span>
        <span
            ref="nextArrowRef"
            class="flicking-arrow-next is-outside cursor-pointer"
            role="button"
            tabindex="0"
            :aria-label="localeValue === 'en' ? 'Next filters' : 'Nächste Filter'"
            @click="handleNext"
            @keydown.enter.prevent="handleNext"
            @keydown.space.prevent="handleNext"
        ></span>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import Flicking from '@egjs/vue3-flicking';
import '@egjs/vue3-flicking/dist/flicking.css';
import { Pagination } from '@egjs/flicking-plugins';
// Arrow plugin removed - using manual click handlers instead for better reliability
import '@egjs/flicking-plugins/dist/pagination.css';
import { useFilterStore } from '../../stores/filter';
import { useLocale } from '../../composables/useLocale';

const props = defineProps({
    group: {
        type: Object,
        required: true,
    },
});

const filterStore = useFilterStore();
const { currentLocale } = useLocale();

// Template refs
const containerRef = ref(null);
const prevArrowRef = ref(null);
const nextArrowRef = ref(null);
const flicking = ref(null);

// Computed values with safe fallbacks
const localeValue = computed(() => currentLocale.value || 'de');
const groupId = computed(() => props.group?.id || 0);
const flickingKey = computed(() => `flicking-${localeValue.value}-${groupId.value}`);

const items = computed(() => props.group?.items || []);
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024);
const panelsPerView = ref(5);
const circular = ref(false);
const align = ref('prev');

// Control when Flicking is ready to render
const isReady = ref(false);
const activePlugins = ref([]);

function getItemTitle(item) {
    if (typeof item.title === 'string') {
        return item.title;
    }
    if (typeof item.title === 'object' && item.title !== null) {
        return item.title[localeValue.value] || item.title.de || item.title.en || '';
    }
    return '';
}

function getItemIcon(item) {
    if (!item.icon) {
        return null;
    }
    if (typeof item.icon === 'string') {
        return item.icon;
    }
    if (typeof item.icon === 'object' && item.icon !== null) {
        return item.icon[localeValue.value] || item.icon.de || item.icon.en || null;
    }
    return null;
}

function getItemKey(item) {
    return item.key || item.id?.toString() || null;
}

function isSelected(item) {
    if (!filterStore.level2Filter?.key) {
        return false;
    }
    const itemKey = getItemKey(item);
    return itemKey === filterStore.level2Filter.key;
}

function selectItem(item) {
    const title = getItemTitle(item);
    const key = getItemKey(item);

    if (!key) {
        return;
    }

    if (isSelected(item)) {
        filterStore.clearLevel2Filter();
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
        align.value = items.value.length < panelsPerView.value ? 'center' : 'prev';
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

// Safe navigation handlers
function handlePrev() {
    if (flicking.value?.vanillaFlicking) {
        flicking.value.vanillaFlicking.prev();
    }
}

function handleNext() {
    if (flicking.value?.vanillaFlicking) {
        flicking.value.vanillaFlicking.next();
    }
}

// Initialize plugins (only Pagination - Arrow functionality handled via click handlers)
function initializePlugins() {
    // Destroy existing plugins first
    if (activePlugins.value && Array.isArray(activePlugins.value)) {
        activePlugins.value.forEach(plugin => {
            if (plugin && typeof plugin.destroy === 'function') {
                try {
                    plugin.destroy();
                } catch (e) {
                    // Ignore destroy errors
                }
            }
        });
    }
    
    // Create only Pagination plugin - Arrow navigation handled via manual click handlers
    // This is more reliable as it doesn't depend on DOM element selectors
    activePlugins.value = [
        new Pagination({ type: 'bullet' })
    ];
}

// Watch for locale changes and reinitialize
watch(localeValue, async (newLocale, oldLocale) => {
    if (newLocale !== oldLocale && oldLocale !== undefined) {
        // Hide Flicking during transition
        isReady.value = false;
        
        // Wait for DOM to update
        await nextTick();
        
        // Reinitialize plugins
        initializePlugins();
        
        // Show Flicking again
        await nextTick();
        isReady.value = true;
    }
}, { immediate: false });

// Sync watcher - fires immediately when items change, BEFORE Vue re-renders
// This ensures align is set correctly before the new Flicking instance reads it
watch(items, () => {
    setCircularAndAlign();
}, { flush: 'sync' });

// Watch for group changes (tab switches) and reinitialize plugins
watch(groupId, async (newId, oldId) => {
    if (newId !== oldId && oldId !== undefined) {
        // Hide Flicking during transition
        isReady.value = false;
        
        // Wait for DOM to update
        await nextTick();
        
        // Reinitialize plugins
        initializePlugins();
        
        // Show Flicking again
        await nextTick();
        isReady.value = true;
    }
}, { immediate: false });

onMounted(async () => {
    setPanelsPerView();
    setCircularAndAlign();
    window.addEventListener('resize', onResize);
    
    // Wait for next tick to ensure DOM is ready
    await nextTick();
    
    // Initialize plugins
    initializePlugins();
    
    // Now ready to show Flicking
    isReady.value = true;
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    
    // Destroy plugins
    if (activePlugins.value && Array.isArray(activePlugins.value)) {
        activePlugins.value.forEach(plugin => {
            if (plugin && typeof plugin.destroy === 'function') {
                try {
                    plugin.destroy();
                } catch (e) {
                    // Ignore destroy errors
                }
            }
        });
    }
    
    // Destroy Flicking instance
    if (flicking.value && flicking.value.vanillaFlicking) {
        try {
            flicking.value.vanillaFlicking.destroy();
        } catch (e) {
            // Ignore destroy errors
        }
    }
});
</script>
