<template>
  <div
    ref="containerRef"
    class="relative"
  >
    <div
      v-if="items.length === 0"
      class="text-center py-8 text-gray-600"
    >
      {{ localeValue === 'en' ? 'No filters available' : 'Keine Filter verfügbar' }}
    </div>
    <Flicking
      v-else-if="isReady"
      :key="flickingKey"
      ref="flicking"
      :class="[needsScrolling ? 'pb-10' : '']"
      :plugins="activePlugins"
      :options="{
        align,
        defaultIndex: 0,
        circular,
        circularFallback: 'bound',
        moveType: 'snap',
        panelsPerView,
        bound: true,
        inputType: ['touch', 'mouse', 'pointer']
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
            @error="onIconError"
          >
        </span>
        <span
          class="block text-xl text-center hyphens-auto break-words max-w-[120px]"
          :lang="localeValue"
        >
          {{ getItemTitle(item) }}
        </span>
      </button>

      <template #viewport>
        <div
          v-show="needsScrolling"
          class="xl:hidden flicking-pagination"
        />
      </template>
    </Flicking>

    <span
      v-show="needsScrolling"
      ref="prevArrowRef"
      class="flicking-arrow-prev flicking-arrow-prev-filter is-outside cursor-pointer"
      role="button"
      tabindex="0"
      :aria-label="localeValue === 'en' ? 'Previous filters' : 'Vorherige Filter'"
      @click="handlePrev"
      @keydown.enter.prevent="handlePrev"
      @keydown.space.prevent="handlePrev"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="36"
        height="36"
        viewBox="0 0 36 36"
        aria-hidden="true"
      >
        <circle
          cx="18"
          cy="18"
          r="18"
          fill="#fff"
        />
        <path
          d="M1.061,1.061l9.238,9.5-9.238,9.5"
          transform="translate(22.806 29.558) rotate(180)"
          fill="none"
          stroke="currentColor"
          stroke-miterlimit="10"
          stroke-width="3"
        />
      </svg>
    </span>
    <span
      v-show="needsScrolling"
      ref="nextArrowRef"
      class="flicking-arrow-next flicking-arrow-next-filter is-outside cursor-pointer"
      role="button"
      tabindex="0"
      :aria-label="localeValue === 'en' ? 'Next filters' : 'Nächste Filter'"
      @click="handleNext"
      @keydown.enter.prevent="handleNext"
      @keydown.space.prevent="handleNext"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="36"
        height="36"
        viewBox="0 0 36 36"
        aria-hidden="true"
      >
        <circle
          cx="18"
          cy="18"
          r="18"
          fill="#fff"
        />
        <path
          d="M0,18.995,9.238,9.5,0,0"
          transform="translate(14.254 9.503)"
          fill="none"
          stroke="currentColor"
          stroke-miterlimit="10"
          stroke-width="3"
        />
      </svg>
    </span>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import Flicking from '@egjs/vue3-flicking';
import '@egjs/vue3-flicking/dist/flicking.css';
import { Pagination, Arrow } from '@egjs/flicking-plugins';
import '@egjs/flicking-plugins/dist/arrow.css';
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

const needsScrolling = computed(() => items.value.length > panelsPerView.value);

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

// Icon URLs that failed to load (e.g. broken/missing files on the backend).
// Once an icon fails, we stop rendering the <img> for it so the browser
// never shows a broken-image placeholder with alt text inside the circle;
// the plain colored circle remains as a neutral fallback.
const brokenIconUrls = ref(new Set());

function getItemIcon(item) {
    if (!item.icon) {
        return null;
    }
    let icon = null;
    if (typeof item.icon === 'string') {
        icon = item.icon;
    } else if (typeof item.icon === 'object' && item.icon !== null) {
        icon = item.icon[localeValue.value] || item.icon.de || item.icon.en || null;
    }
    if (icon && brokenIconUrls.value.has(icon)) {
        return null;
    }
    return icon;
}

function onIconError(event) {
    // Use the raw `src` attribute (not the `.src` IDL property, which the browser
    // resolves to an absolute URL) so it matches the value returned by getItemIcon()
    // — item.icon is a root-relative "/storage/..." path. Comparing against the
    // resolved absolute URL would never match, leaving the broken icon on screen.
    const src = event?.target?.getAttribute('src');
    if (!src) {
        return;
    }
    // Replace the Set so Vue reliably detects the change and re-renders.
    brokenIconUrls.value = new Set(brokenIconUrls.value).add(src);
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

// Initialize plugins (Pagination + Arrow with parentEl scoping)
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
    
    const plugins = [new Pagination({ type: 'bullet' })];

    // Add Arrow plugin with parentEl to scope selectors to this component
    if (containerRef.value) {
        plugins.push(new Arrow({
            parentEl: containerRef.value,
            prevElSelector: '.flicking-arrow-prev-filter',
            nextElSelector: '.flicking-arrow-next-filter',
        }));
    }

    activePlugins.value = plugins;
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
