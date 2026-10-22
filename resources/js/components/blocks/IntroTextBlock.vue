<template>
  <div class="container">
    <h1
      v-if="block.props.heading"
      class="content-heading mb-6 hyphens-auto"
    >
      {{ block.props.heading }}
    </h1>

    <div class="lg:grid lg:grid-cols-3 lg:gap-10 mb-10 lg:mb-14">
      <div class="col-span-2">
        <h2
          v-if="block.props.subheading"
          class="content-heading mb-6 lg:mb-12 hyphens-auto"
        >
          {{ block.props.subheading }}
        </h2>
                
        <BlockImage
          v-if="imageUrl"
          class="lg:hidden mb-6"
          :src="imageUrl"
          :alt="block.props.image_alt"
          :title="block.props.image_title"
          :copyright="block.props.image_copyright"
        />

        <div
          v-if="block.props.text"
          class="prose prose-lg max-w-none [&_p]:text-xl [&_p]:text-black [&_p]:mb-5 [&_p:last-child]:mb-10 [&_li]:text-xl [&_li]:text-black [&_a]:transition-colors [&_a]:duration-300"
          v-html="sanitizedText"
        />
      </div>
            
      <div class="col-span-1">
        <BlockImage
          v-if="imageUrl"
          class="hidden lg:block mb-10"
          :src="imageUrl"
          :alt="block.props.image_alt"
          :title="block.props.image_title"
          :copyright="block.props.image_copyright"
        />
        <BlockImage
          v-if="imageSecondaryUrl"
          class="hidden lg:block"
          :src="imageSecondaryUrl"
          :alt="block.props.image_secondary_alt"
          :title="block.props.image_secondary_title"
          :copyright="block.props.image_secondary_copyright"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, toRef } from 'vue';
import { sanitizeHtml } from '../../utils/sanitizeHtml';
import { useImageUrl } from '../../composables/useImageUrl';
import BlockImage from '../BlockImage.vue';

const props = defineProps({
    block: {
        type: Object,
        required: true,
    },
});

// Get image URLs using composable
const imageUrl = useImageUrl(toRef(() => props.block.props.image));
const imageSecondaryUrl = useImageUrl(toRef(() => props.block.props.image_secondary));

const sanitizedText = computed(() => {
    return props.block.props.text ? sanitizeHtml(props.block.props.text) : '';
});
</script>



