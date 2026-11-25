<!--
SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG

SPDX-License-Identifier: EUPL-1.2+
-->

<template>
  <button
    type="button"
    class="h-9 w-9 rounded-full flex items-center justify-center shrink-0 hover:shadow-info transition-shadow duration-200"
    :style="{ color: brandingStore.primaryColor }"
    :aria-label="labels.share"
    :title="copied ? labels.copied : labels.share"
    @click="share"
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
      aria-hidden="true"
    >
      <circle
        cx="18"
        cy="5"
        r="3"
      />
      <circle
        cx="6"
        cy="12"
        r="3"
      />
      <circle
        cx="18"
        cy="19"
        r="3"
      />
      <line
        x1="8.59"
        y1="13.51"
        x2="15.42"
        y2="17.49"
      />
      <line
        x1="15.41"
        y1="6.51"
        x2="8.59"
        y2="10.49"
      />
    </svg>
  </button>
  <span
    class="sr-only"
    aria-live="polite"
  >{{ copied ? labels.copied : '' }}</span>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { useLocale } from '../../../composables/useLocale';
import { useBrandingStore } from '../../../stores/branding';

/**
 * Shares the public tile URL. Its page carries the tile's Open Graph preview image,
 * so messengers and social networks show a card of the tile.
 */
const props = defineProps({
    tileSlug: { type: String, required: true },
    tileTitle: { type: String, default: null },
});

const brandingStore = useBrandingStore();
const { currentLocale } = useLocale();
const copied = ref(false);
let resetTimer = null;
onBeforeUnmount(() => clearTimeout(resetTimer));

const labels = computed(() => (currentLocale.value === 'en'
    ? { share: 'Share tile', copied: 'Link copied' }
    : { share: 'Kachel teilen', copied: 'Link kopiert' }));

const url = computed(() => {
    const prefix = currentLocale.value === 'en' ? '/en' : '';
    return `${window.location.origin}${prefix}/tiles/${encodeURIComponent(props.tileSlug)}`;
});

async function share() {
    if (navigator.share) {
        try {
            await navigator.share({ title: props.tileTitle || undefined, url: url.value });
            return;
        } catch (error) {
            // The user closed the share sheet; nothing to fall back to.
            if (error?.name === 'AbortError') return;
        }
    }

    try {
        await navigator.clipboard.writeText(url.value);
        copied.value = true;
        clearTimeout(resetTimer);
        resetTimer = setTimeout(() => (copied.value = false), 3000);
    } catch {
        // Clipboard blocked (insecure context or denied): offer the URL to copy by hand.
        window.prompt(labels.value.share, url.value);
    }
}
</script>
