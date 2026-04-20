<template>
  <Teleport to="body">
    <div
      v-if="open"
      ref="dialogRoot"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 text-left"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      @click.self="close"
      @keydown.esc="close"
      @keydown.tab="onTab"
    >
      <div class="bg-white shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto px-6">
        <div class="py-6 border-b border-gray-200 flex items-start justify-between gap-4">
          <div>
            <h2
              :id="titleId"
              class="text-xl font-bold text-gray-900"
            >
              {{ titleWithScope }}
            </h2>
            <p class="mt-2 text-sm text-gray-700">
              {{ labels.description }}
            </p>
          </div>
          <button
            ref="closeButton"
            type="button"
            class="text-gray-400 hover:text-gray-600 shrink-0"
            :aria-label="labels.cancel"
            @click="close"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path d="M18 6L6 18" /><path d="M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form
          :id="formId"
          class="py-6 space-y-6"
          @submit.prevent="submit"
        >
          <fieldset>
            <legend class="text-sm font-semibold text-gray-900 mb-2">
              {{ labels.format }}
            </legend>
            <div class="flex gap-4">
              <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                <input
                  v-model="format"
                  type="radio"
                  value="json"
                  class="sr-only"
                >
                <span
                  class="w-5 h-5 rounded-full border flex items-center justify-center shrink-0"
                  :style="radioStyle('json')"
                  aria-hidden="true"
                >
                  <span
                    v-if="format === 'json'"
                    class="w-2 h-2 rounded-full bg-white"
                  />
                </span>
                <span>{{ labels.formatJson }}</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                <input
                  v-model="format"
                  type="radio"
                  value="csv"
                  class="sr-only"
                >
                <span
                  class="w-5 h-5 rounded-full border flex items-center justify-center shrink-0"
                  :style="radioStyle('csv')"
                  aria-hidden="true"
                >
                  <span
                    v-if="format === 'csv'"
                    class="w-2 h-2 rounded-full bg-white"
                  />
                </span>
                <span>{{ labels.formatCsv }}</span>
              </label>
            </div>
          </fieldset>

          <fieldset>
            <legend class="text-sm font-semibold text-gray-900 mb-2">
              {{ labels.fields }}
            </legend>
            <div class="space-y-3">
              <label
                v-for="group in fieldGroups"
                :key="group.key"
                class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer"
              >
                <input
                  type="checkbox"
                  class="sr-only"
                  :checked="isGroupFullySelected(group)"
                  @change="toggleGroup(group, $event.target.checked)"
                >
                <span
                  class="w-5 h-5 border flex items-center justify-center shrink-0"
                  :style="checkboxStyle(group)"
                  aria-hidden="true"
                >
                  <svg
                    v-if="isGroupFullySelected(group)"
                    xmlns="http://www.w3.org/2000/svg"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="#ffffff"
                    stroke-width="3"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <polyline points="20 6 9 17 4 12" />
                  </svg>
                  <svg
                    v-else-if="isGroupPartiallySelected(group)"
                    xmlns="http://www.w3.org/2000/svg"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="#ffffff"
                    stroke-width="3"
                    stroke-linecap="round"
                  >
                    <line
                      x1="5"
                      y1="12"
                      x2="19"
                      y2="12"
                    />
                  </svg>
                </span>
                <span>{{ labels.groups[group.key] }}</span>
              </label>
            </div>
          </fieldset>

          <div
            v-if="localError"
            class="text-sm text-red-600"
            role="alert"
          >
            {{ localError }}
          </div>
        </form>

        <div class="py-6 border-t border-gray-200 flex justify-end gap-3">
          <button
            type="button"
            class="px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 rounded"
            @click="close"
          >
            {{ labels.cancel }}
          </button>
          <button
            type="submit"
            :form="formId"
            :disabled="loading || selectedFields.length === 0"
            class="px-4 py-2 text-sm font-semibold text-white rounded disabled:opacity-50"
            :style="{ backgroundColor: brandingStore.primaryColor }"
          >
            {{ loading ? labels.downloading : labels.download }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useExport } from '../../composables/useExport';
import { useLocale } from '../../composables/useLocale';
import { useFilterStore } from '../../stores/filter';
import { useBrandingStore } from '../../stores/branding';

/**
 * Reusable export dialog. Works in three scopes:
 *  - `tile`    — needs `tileSlug`, exports a single tile
 *  - `filtered` — uses the current filter store state (tiles/categories/years)
 *  - `catalog` — exports the whole tenant catalog
 */
const props = defineProps({
    open: { type: Boolean, required: true },
    scope: {
        type: String,
        required: true,
        validator: (value) => ['tile', 'filtered', 'catalog'].includes(value),
    },
    tileSlug: { type: String, default: null },
    tileTitle: { type: String, default: null },
});

const emit = defineEmits(['close']);

const brandingStore = useBrandingStore();
const filterStore = useFilterStore();
const { currentLocale } = useLocale();
const { download, tileUrl, filteredTilesUrl, catalogUrl, loading } = useExport();

const titleId = `export-dialog-title-${Math.random().toString(36).slice(2)}`;
const formId = `export-dialog-form-${Math.random().toString(36).slice(2)}`;
const format = ref('json');
const localError = ref('');
const dialogRoot = ref(null);
const closeButton = ref(null);
let previouslyFocused = null;

const fieldGroups = [
    {
        key: 'tile',
        fields: ['tile.id', 'tile.slug', 'tile.title', 'tile.description', 'tile.hint', 'tile.position'],
    },
    {
        key: 'category',
        fields: ['category.keys', 'category.labels', 'category.groups'],
    },
    {
        key: 'metric',
        fields: [
            'metric.key',
            'metric.label',
            'metric.unit',
            'metric.indicator_type',
            'metric.source',
            'metric.source_url',
            'metric.methodology',
            'metric.formula',
        ],
    },
    {
        key: 'value',
        fields: ['value.year', 'value.value', 'value.sort_order'],
    },
];

const selectedFields = ref(fieldGroups.flatMap((g) => g.fields));

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        format.value = 'json';
        selectedFields.value = fieldGroups.flatMap((g) => g.fields);
        localError.value = '';
        // Cache the element that had focus before the dialog opened so we
        // can restore focus on close (WCAG 2.4.3, focus order).
        previouslyFocused = typeof document !== 'undefined' ? document.activeElement : null;
        // Move focus into the dialog so keyboard users land inside the modal.
        nextTick(() => {
            closeButton.value?.focus();
        });
    } else {
        // Restore focus to the element that opened the dialog.
        if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
            previouslyFocused.focus();
        }
        previouslyFocused = null;
    }
});

onBeforeUnmount(() => {
    if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
        previouslyFocused.focus();
    }
    previouslyFocused = null;
});

/**
 * Simple focus trap: when the user tabs past the last focusable element,
 * wrap to the first, and vice versa. Keeps keyboard focus inside the modal
 * as required for `aria-modal="true"` dialogs.
 */
function onTab(event) {
    const root = dialogRoot.value;
    if (!root) return;
    const focusables = Array.from(
        root.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )
    ).filter((el) => !el.hasAttribute('disabled') && el.offsetParent !== null);
    if (focusables.length === 0) return;
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    const active = document.activeElement;
    if (event.shiftKey) {
        if (active === first || !root.contains(active)) {
            event.preventDefault();
            last.focus();
        }
    } else if (active === last) {
        event.preventDefault();
        first.focus();
    }
}

const translations = {
    de: {
        title: 'Daten exportieren',
        description: 'Wählen Sie Format und Felder für den Export aus.',
        format: 'Format',
        formatJson: 'JSON',
        formatCsv: 'CSV (Excel-kompatibel)',
        fields: 'Felder',
        download: 'Herunterladen',
        downloading: 'Wird vorbereitet …',
        cancel: 'Abbrechen',
        error: 'Der Export konnte nicht erstellt werden.',
        scopeTile: 'Kachel',
        scopeFiltered: 'Aktuell gefilterte Kacheln',
        scopeCatalog: 'Alle Kacheln',
        groups: {
            tile: 'Kachel-Metadaten',
            category: 'Kategorien',
            metric: 'Metriken',
            value: 'Werte & Jahre',
        },
    },
    en: {
        title: 'Export data',
        description: 'Pick the format and fields for your export.',
        format: 'Format',
        formatJson: 'JSON',
        formatCsv: 'CSV (Excel-friendly)',
        fields: 'Fields',
        download: 'Download',
        downloading: 'Preparing …',
        cancel: 'Cancel',
        error: 'The export could not be generated.',
        scopeTile: 'Tile',
        scopeFiltered: 'Currently filtered tiles',
        scopeCatalog: 'All tiles',
        groups: {
            tile: 'Tile metadata',
            category: 'Categories',
            metric: 'Metrics',
            value: 'Values & years',
        },
    },
};

const labels = computed(() => translations[currentLocale.value] || translations.de);

const scopePreview = computed(() => {
    if (props.scope === 'tile') {
        return `${labels.value.scopeTile}: ${props.tileTitle || props.tileSlug}`;
    }
    if (props.scope === 'filtered') {
        return labels.value.scopeFiltered;
    }
    // catalog scope is implicit in the "Export all tiles" trigger button —
    // keep the headline clean instead of repeating "Alle Kacheln" in parens.
    return '';
});

const titleWithScope = computed(() =>
    scopePreview.value
        ? `${labels.value.title} (${scopePreview.value})`
        : labels.value.title
);

function isGroupFullySelected(group) {
    return group.fields.every((f) => selectedFields.value.includes(f));
}

function isGroupPartiallySelected(group) {
    const count = group.fields.filter((f) => selectedFields.value.includes(f)).length;
    return count > 0 && count < group.fields.length;
}

function checkboxStyle(group) {
    const active = isGroupFullySelected(group) || isGroupPartiallySelected(group);
    return active
        ? { backgroundColor: brandingStore.primaryColor, borderColor: brandingStore.primaryColor }
        : { backgroundColor: 'transparent', borderColor: '#d1d5db' };
}

function radioStyle(value) {
    return format.value === value
        ? { backgroundColor: brandingStore.primaryColor, borderColor: brandingStore.primaryColor }
        : { backgroundColor: 'transparent', borderColor: '#d1d5db' };
}

function toggleGroup(group, enabled) {
    if (enabled) {
        const next = new Set(selectedFields.value);
        for (const f of group.fields) next.add(f);
        selectedFields.value = Array.from(next);
    } else {
        selectedFields.value = selectedFields.value.filter((f) => !group.fields.includes(f));
    }
}

function close() {
    emit('close');
}

async function submit() {
    localError.value = '';

    const params = {
        format: format.value,
        locale: currentLocale.value,
        fields: selectedFields.value,
    };

    let url;
    let filename;

    if (props.scope === 'tile') {
        url = tileUrl(props.tileSlug, params);
        filename = `tile-${props.tileSlug}.${format.value}`;
    } else if (props.scope === 'filtered') {
        if (filterStore.level2Filter?.key) {
            params.categories = [filterStore.level2Filter.key];
        }
        url = filteredTilesUrl(params);
        filename = `tiles.${format.value}`;
    } else {
        url = catalogUrl(params);
        filename = `catalog.${format.value}`;
    }

    try {
        await download(url, filename);
        close();
    } catch {
        localError.value = labels.value.error;
    }
}
</script>
