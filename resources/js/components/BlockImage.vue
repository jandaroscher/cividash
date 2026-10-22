<template>
  <figure class="relative">
    <img
      :src="src"
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
});

const copyrightText = computed(() => {
    const text = (props.copyright || '').trim();
    if (!text) return '';
    return text.startsWith('©') ? text : `© ${text}`;
});
</script>
