<template>
  <div class="flex space-x-2.5 mb-4">
    <img
      v-if="imageUrl"
      class="w-20 object-contain"
      width="80"
      height="80"
      :src="imageUrl"
      :alt="title"
      loading="lazy"
    >

    <template v-if="lottieUrl">
      <div
        v-intersection-observer="onIntersectionObserver"
        class="w-20 h-20"
      >
        <dotlottie-wc
          ref="lottiePlayer"
          autoplay="true"
          loop="true"
          class="w-20 h-20 object-contain"
        />
      </div>
    </template>

    <div>
      <div class="text-theme-base text-black mb-2.5">
        {{ title }}
      </div>
      <div
        v-if="indicatorValue === null || indicatorValue === undefined || indicatorValue === ''"
        class="text-black text-theme-h5 font-bold"
      >
        -
      </div>

      <div
        v-else
        class="text-black text-theme-h5 font-bold flex flex-row space-x-1 items-center"
      >
        <span
          v-if="arrow"
          class="inline-block top-px relative mr-1"
        >
          <img
            v-show="arrowType === 'up'"
            :src="arrowUpUrl"
            width="24"
            height="25"
            alt=""
          >
          <img
            v-show="arrowType === 'normal'"
            :src="arrowStraightUrl"
            width="24"
            height="24"
            alt=""
          >
          <img
            v-show="arrowType === 'down'"
            :src="arrowDownUrl"
            width="24"
            height="25"
            alt=""
          >
        </span>
        {{ formattedValue }} {{ unit }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import '../../../lib/dotlottie';
import { useIndicator } from '../../../composables/useIndicator';

const props = defineProps({
    indicator: {
        type: Object,
        required: true,
    },
    currentYear: {
        type: [String, Number],
        required: true,
    },
});

// Use shared indicator logic composable
const indicatorComposable = useIndicator(
    computed(() => props.indicator),
    computed(() => props.currentYear)
);

// Destructure composable return values
const {
    lottiePlayer,
    lottieUrl,
    imageUrl,
    title,
    unit,
    arrow,
    arrowUpUrl,
    arrowStraightUrl,
    arrowDownUrl,
    indicatorValue,
    formattedValue,
    arrowType,
    onIntersectionObserver,
} = indicatorComposable;
</script>

