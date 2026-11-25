<template>
  <figure class="relative max-w-full">
    <img
      :src="src"
      :srcset="srcset"
      :sizes="srcset ? sizes : undefined"
      :alt="alt || ''"
      :title="title || undefined"
      :class="['block', imgClass]"
      loading="lazy"
    >
    <figcaption
      v-if="copyrightText"
      class="absolute bottom-0 right-0 max-w-full bg-black/60 px-1.5 py-0.5 text-xs text-white"
    >
      {{ copyrightText }}
    </figcaption>
  </figure>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    src: { type: String, required: true },
    alt: { type: String, default: '' },
    title: { type: String, default: '' },
    copyright: { type: String, default: '' },
    imgClass: { type: [String, Array, Object], default: '' },
    // Rendered width of the image, so the browser picks the smallest fitting copy.
    sizes: { type: String, default: '(min-width: 768px) 50vw, 100vw' },
});

// Widths the server scales uploads to (ResizedMediaController::WIDTHS).
const RESIZED_WIDTHS = [480, 960, 1600];

const srcset = computed(() => {
    const match = /^\/storage\/(?!_resized\/)(.+\.(?:jpe?g|png|webp))$/i.exec(props.src);
    if (!match) return undefined;
    // srcset splits candidates on whitespace and commas, so both must be escaped.
    let path;
    try {
        path = encodeURI(decodeURI(match[1])).replaceAll(',', '%2C');
    } catch {
        return undefined;
    }
    return RESIZED_WIDTHS.map((w) => `/storage/_resized/${w}/${path} ${w}w`).join(', ');
});

const copyrightText = computed(() => {
    const text = (props.copyright || '').trim();
    if (!text) return '';
    return text.startsWith('©') ? text : `© ${text}`;
});
</script>
