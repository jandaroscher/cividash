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
    // Rendered width of the image. Set it only where CSS fixes the image width:
    // with a srcset the browser renders an unstyled image at this width.
    sizes: { type: String, default: '' },
});

// Widths the server scales uploads to (ResizedMediaController::WIDTHS).
const RESIZED_WIDTHS = [480, 960, 1600];

const srcset = computed(() => {
    if (!props.sizes) return undefined;
    const src = props.src.startsWith(window.location.origin) ? props.src.slice(window.location.origin.length) : props.src;
    const match = /^\/storage\/(?!_resized\/)(.+\.(?:jpe?g|png|webp))$/i.exec(src);
    if (!match) return undefined;
    // srcset splits candidates on whitespace and commas, so encode each segment.
    let path;
    try {
        path = match[1].split('/').map((segment) => encodeURIComponent(decodeURIComponent(segment))).join('/');
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
