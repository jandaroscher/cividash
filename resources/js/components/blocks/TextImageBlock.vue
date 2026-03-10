<template>
  <section class="py-12 md:py-16">
    <div class="container">
      <h2
        v-if="heading"
        class="text-theme-h2 font-bold mb-6"
      >
        {{ heading }}
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
        <div
          v-if="isImageLeft && imageUrl"
          class="order-1"
        >
          <img
            :src="imageUrl"
            :alt="altText"
            loading="lazy"
            class="w-full h-auto rounded-lg shadow-card"
          >
        </div>
        <div :class="['order-2', isImageLeft ? '' : 'md:order-1']">
          <div
            v-if="block.props.text"
            class="prose prose-lg max-w-none mb-6 lg:mb-12 [&_a]:transition-colors [&_a]:duration-300"
            v-html="sanitizedText"
          />
        </div>
        <div
          v-if="!isImageLeft && imageUrl"
          class="order-1 md:order-2"
        >
          <img
            :src="imageUrl"
            :alt="altText"
            loading="lazy"
            class="w-full h-auto rounded-lg shadow-card"
          >
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import { sanitizeHtml } from '../../utils/sanitizeHtml';
import { useLocale } from '../../composables/useLocale';

const props = defineProps({
    block: {
        type: Object,
        required: true,
    },
});

const { currentLocale } = useLocale();

const heading = computed(() => {
    if (currentLocale.value === 'en') {
        return props.block.props.heading_en || props.block.props.heading || '';
    }
    return props.block.props.heading || props.block.props.heading_en || '';
});

const imageUrl = computed(() => {
    if (!props.block.props.image) return null;
    if (props.block.props.image.startsWith('http')) {
        return props.block.props.image;
    }
    return `/storage/${props.block.props.image}`;
});

const isImageLeft = computed(() => {
    return (props.block.props.image_position || 'left') === 'left';
});

const sanitizedText = computed(() => {
    return props.block.props.text ? sanitizeHtml(props.block.props.text) : '';
});

const altText = computed(() => {
    return props.block.props.image_alt || 'Image';
});
</script>
