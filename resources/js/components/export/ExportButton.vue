<template>
  <template v-if="mode === 'direct'">
    <div class="relative inline-flex items-center">
      <button
        type="button"
        class="rounded-full shrink-0 w-9 h-9 inline-flex items-center justify-center hover:shadow-info transition-shadow duration-200 disabled:opacity-50"
        :style="{ color: brandingStore.primaryColor }"
        :aria-label="labels.button"
        :disabled="loading || !canDirectDownload"
        :title="directError || undefined"
        @click="directDownload"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="22"
          height="22"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
          <polyline points="7 10 12 15 17 10" />
          <line
            x1="12"
            y1="15"
            x2="12"
            y2="3"
          />
        </svg>
      </button>
      <span
        v-if="directError"
        class="absolute right-full mr-2 whitespace-nowrap text-xs font-medium text-red-600 bg-white/95 border border-red-200 rounded px-2 py-1 shadow-sm"
        role="alert"
      >
        {{ directError }}
      </span>
    </div>
  </template>
  <template v-else>
    <button
      type="button"
      class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold hover:opacity-90 transition"
      :style="{ backgroundColor: brandingStore.primaryColor, color: '#fff' }"
      @click="dialogOpen = true"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="16"
        height="16"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
        <polyline points="7 10 12 15 17 10" />
        <line
          x1="12"
          y1="15"
          x2="12"
          y2="3"
        />
      </svg>
      <span>{{ buttonLabel }}</span>
    </button>
    <ExportDialog
      :open="dialogOpen"
      :scope="scope"
      :tile-slug="tileSlug"
      :tile-title="tileTitle"
      @close="dialogOpen = false"
    />
  </template>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import ExportDialog from './ExportDialog.vue';
import { useBrandingStore } from '../../stores/branding';
import { useExport } from '../../composables/useExport';
import { useLocale } from '../../composables/useLocale';

/**
 * Universal export trigger.
 *
 * Modes:
 *  - `dialog`     — opens the ExportDialog so the user can pick format + fields
 *  - `direct`     — bypasses the dialog and triggers a CSV default download
 *                   (used for the compact card-icon action)
 */
const props = defineProps({
    mode: {
        type: String,
        default: 'dialog',
        validator: (v) => ['dialog', 'direct'].includes(v),
    },
    scope: {
        type: String,
        default: 'tile',
        validator: (v) => ['tile', 'filtered', 'catalog'].includes(v),
    },
    tileSlug: { type: String, default: null },
    tileTitle: { type: String, default: null },
    label: { type: String, default: null },
});

const brandingStore = useBrandingStore();
const { currentLocale } = useLocale();
const { download, tileUrl, loading } = useExport();

const dialogOpen = ref(false);

const translations = {
    de: {
        button: 'Daten herunterladen',
        buttonCatalog: 'Alle Kacheln exportieren',
        buttonFiltered: 'Kacheln exportieren',
        errorGeneric: 'Download fehlgeschlagen.',
        errorRateLimit: 'Zu viele Anfragen. Bitte kurz warten.',
    },
    en: {
        button: 'Download data',
        buttonCatalog: 'Export all tiles',
        buttonFiltered: 'Export tiles',
        errorGeneric: 'Download failed.',
        errorRateLimit: 'Too many requests. Please wait a moment.',
    },
};
/**
 * Normalize region-tagged locales (e.g. `en-US`, `de_AT`) to the base
 * language so the translations table still resolves.
 */
function baseLocale(locale) {
    const raw = String(locale || '').toLowerCase();
    if (raw.startsWith('en')) return 'en';
    if (raw.startsWith('de')) return 'de';
    return 'de';
}
const labels = computed(() => translations[baseLocale(currentLocale.value)]);

const directError = ref('');
let directErrorTimer = null;

const canDirectDownload = computed(() => props.scope === 'tile' && Boolean(props.tileSlug));

const buttonLabel = computed(() => {
    if (props.label) return props.label;
    if (props.scope === 'catalog') return labels.value.buttonCatalog;
    if (props.scope === 'filtered') return labels.value.buttonFiltered;
    return labels.value.button;
});

async function directDownload() {
    if (!canDirectDownload.value) return;
    const url = tileUrl(props.tileSlug, { format: 'csv', locale: currentLocale.value });
    directError.value = '';
    if (directErrorTimer) {
        clearTimeout(directErrorTimer);
        directErrorTimer = null;
    }
    try {
        await download(url, `tile-${props.tileSlug}.csv`);
    } catch (e) {
        // Surface the failure to the user. 429 gets a dedicated hint so
        // people understand it is transient and not a broken button.
        const message = /HTTP 429/.test(String(e?.message || ''))
            ? labels.value.errorRateLimit
            : labels.value.errorGeneric;
        directError.value = message;
        directErrorTimer = setTimeout(() => {
            directError.value = '';
            directErrorTimer = null;
        }, 5000);
        logError('[ExportButton] download failed', e);
    }
}

onBeforeUnmount(() => {
    if (directErrorTimer) {
        clearTimeout(directErrorTimer);
        directErrorTimer = null;
    }
});
</script>
