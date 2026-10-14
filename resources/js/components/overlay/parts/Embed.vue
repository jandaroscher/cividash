<template>
  <div class="px-6 xl:px-20 py-8 flex flex-col gap-6">
    <div>
      <h2
        :id="headingId"
        class="content-heading text-theme-h2"
      >
        {{ labels.title }}
      </h2>
      <p class="mt-2 text-sm text-gray-600">
        {{ labels.description }}
      </p>
    </div>

    <div
      v-for="snippet in snippets"
      :key="snippet.key"
    >
      <label
        :for="`${headingId}-${snippet.key}`"
        class="block font-semibold mb-1"
      >
        {{ snippet.label }}
      </label>
      <p class="text-sm text-gray-600 mb-2">
        {{ snippet.hint }}
      </p>
      <textarea
        :id="`${headingId}-${snippet.key}`"
        readonly
        rows="4"
        class="w-full border p-2 font-mono text-xs"
        :style="dividerStyle"
        :value="snippet.code"
        @focus="$event.target.select()"
      />
      <button
        type="button"
        class="mt-2 px-4 py-2 text-sm font-semibold"
        :style="{ backgroundColor: brandingStore.primaryColor, color: inverseColor }"
        @click="copy(snippet)"
      >
        {{ copied === snippet.key ? labels.copied : labels.copy }}
      </button>
    </div>
    <p
      class="sr-only"
      aria-live="polite"
    >
      {{ copied ? labels.copied : '' }}
    </p>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useLocale } from '../../../composables/useLocale';
import { useBrandingStore } from '../../../stores/branding';
import { buildEmbedSnippets } from '../../../utils/embedSnippets';

/**
 * Embed codes for a tile, shown in the tile overlay (like the info content).
 */
const props = defineProps({
    tileSlug: { type: String, required: true },
    tileTitle: { type: String, default: null },
});

const brandingStore = useBrandingStore();
const { currentLocale } = useLocale();

const headingId = `embed-${Math.random().toString(36).slice(2)}`;
const copied = ref(null);

const translations = {
    de: {
        title: 'Kachel einbetten',
        description: 'Kopieren Sie den Code in Ihre Webseite, um die Kachel dort anzuzeigen.',
        loader: 'Einbettung mit automatischer Höhe (empfohlen)',
        loaderHint: 'Die Höhe passt sich dem Inhalt an. Ihre Seite muss Skripte von diesem Dashboard erlauben.',
        iframe: 'Nur iFrame',
        iframeHint: 'Für Seiten ohne Skripte. Die Höhe ist fest und muss bei Bedarf angepasst werden.',
        copy: 'Code kopieren',
        copied: 'Kopiert',
    },
    en: {
        title: 'Embed tile',
        description: 'Copy the code into your website to show the tile there.',
        loader: 'Embed with automatic height (recommended)',
        loaderHint: 'The height follows the content. Your page must allow scripts from this dashboard.',
        iframe: 'iframe only',
        iframeHint: 'For pages without scripts. The height is fixed; adjust it if needed.',
        copy: 'Copy code',
        copied: 'Copied',
    },
};

const labels = computed(() => translations[currentLocale.value] || translations.de);

const snippets = computed(() => {
    const code = buildEmbedSnippets({
        origin: window.location.origin,
        slug: props.tileSlug,
        locale: currentLocale.value,
        title: props.tileTitle,
    });
    return [
        { key: 'loader', label: labels.value.loader, hint: labels.value.loaderHint, code: code.loader },
        { key: 'iframe', label: labels.value.iframe, hint: labels.value.iframeHint, code: code.iframe },
    ];
});

watch(() => props.tileSlug, () => { copied.value = null; });

async function copy(snippet) {
    try {
        await navigator.clipboard.writeText(snippet.code);
        copied.value = snippet.key;
    } catch {
        // Clipboard API blocked (insecure context, permissions): the textarea
        // selects its content on focus, so users can still copy manually.
        document.getElementById(`${headingId}-${snippet.key}`)?.focus();
    }
}

const dividerStyle = { borderColor: 'var(--divider-color, #E5E7EB)' };

const inverseColor = computed(() =>
    brandingStore.textInverseColor || 'var(--text-inverse-color, #FFFFFF)'
);
</script>
