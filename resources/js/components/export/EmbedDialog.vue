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
      <div
        class="shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto px-6"
        :style="surfaceStyle"
      >
        <div
          class="py-6 border-b flex items-start justify-between gap-4"
          :style="dividerStyle"
        >
          <div>
            <h2
              :id="titleId"
              class="text-xl font-bold"
              :style="textPrimaryStyle"
            >
              {{ labels.title }}
            </h2>
            <p
              class="mt-2 text-sm"
              :style="textMutedStyle"
            >
              {{ labels.description }}
            </p>
          </div>
          <button
            ref="closeButton"
            type="button"
            class="shrink-0 hover:opacity-70 transition-opacity"
            :style="textMutedStyle"
            :aria-label="labels.close"
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

        <div class="py-6 space-y-6">
          <div
            v-for="snippet in snippets"
            :key="snippet.key"
          >
            <label
              :for="`${titleId}-${snippet.key}`"
              class="block text-sm font-semibold mb-1"
              :style="textPrimaryStyle"
            >
              {{ snippet.label }}
            </label>
            <p
              class="text-sm mb-2"
              :style="textMutedStyle"
            >
              {{ snippet.hint }}
            </p>
            <textarea
              :id="`${titleId}-${snippet.key}`"
              readonly
              rows="4"
              class="w-full border p-2 font-mono text-xs"
              :style="[textPrimaryStyle, dividerStyle]"
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
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useLocale } from '../../composables/useLocale';
import { useBrandingStore } from '../../stores/branding';
import { buildEmbedSnippets } from '../../utils/embedSnippets';

const props = defineProps({
    open: { type: Boolean, required: true },
    tileSlug: { type: String, required: true },
    tileTitle: { type: String, default: null },
});

const emit = defineEmits(['close']);

const brandingStore = useBrandingStore();
const { currentLocale } = useLocale();

const titleId = `embed-dialog-title-${Math.random().toString(36).slice(2)}`;
const dialogRoot = ref(null);
const closeButton = ref(null);
const copied = ref(null);
let previouslyFocused = null;

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
        close: 'Schließen',
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
        close: 'Close',
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

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        copied.value = null;
        // Restore focus to the trigger on close (WCAG 2.4.3, focus order).
        previouslyFocused = document.activeElement;
        nextTick(() => closeButton.value?.focus());
    } else {
        previouslyFocused?.focus?.();
        previouslyFocused = null;
    }
});

// Keep keyboard focus inside the modal, as required for aria-modal dialogs.
function onTab(event) {
    const focusables = Array.from(dialogRoot.value?.querySelectorAll('button, textarea') ?? []);
    if (focusables.length === 0) return;
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

async function copy(snippet) {
    try {
        await navigator.clipboard.writeText(snippet.code);
        copied.value = snippet.key;
    } catch {
        // Clipboard API blocked (insecure context, permissions): the textarea
        // selects its content on focus, so users can still copy manually.
        document.getElementById(`${titleId}-${snippet.key}`)?.focus();
    }
}

function close() {
    emit('close');
}

const surfaceStyle = computed(() => ({
    backgroundColor: brandingStore.cardBackgroundColor || 'var(--card-background-color, #FFFFFF)',
}));

const textPrimaryStyle = { color: 'var(--text-primary-color, #000000)' };
const textMutedStyle = { color: 'var(--text-primary-color, #000000)', opacity: 0.7 };
const dividerStyle = { borderColor: 'var(--divider-color, #E5E7EB)' };

const inverseColor = computed(() =>
    brandingStore.textInverseColor || 'var(--text-inverse-color, #FFFFFF)'
);
</script>
