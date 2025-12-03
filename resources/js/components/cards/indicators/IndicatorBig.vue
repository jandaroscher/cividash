<template>
    <div class="text-center" ref="target">
        <template v-if="lottieUrl">
            <div
                v-intersection-observer="onIntersectionObserver"
                class="h-[216px]"
            >
                <dotlottie-player
                    ref="lottiePlayer"
                    autoplay="true"
                    loop="true"
                    class="mx-auto h-[216px]"
                />
            </div>
        </template>

        <img
            v-if="imageUrl"
            class="mx-auto h-[216px]"
            height="216"
            width="363"
            :src="imageUrl"
            :alt="title"
            loading="lazy"
        />

        <div v-if="title" class="text-base text-black mb-1 px-4">{{ title }}</div>

        <div v-if="!indicatorValue" class="text-black text-3xl font-bold">-</div>

        <div
            v-else
            class="text-black text-3xl font-bold"
        >
            <span v-if="arrow" class="inline-block top-px relative">
                <img
                    v-show="arrowType === 'up'"
                    :src="arrowUpUrl"
                    width="24"
                    height="25"
                    alt=""
                />
                <img
                    v-show="arrowType === 'normal'"
                    :src="arrowStraightUrl"
                    width="24"
                    height="24"
                    alt=""
                />
                <img
                    v-show="arrowType === 'down'"
                    :src="arrowDownUrl"
                    width="24"
                    height="25"
                    alt=""
                />
            </span>
            {{ formattedValue }} {{ unit }}
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { DotLottiePlayer } from '@johanaarstein/dotlottie-player';
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

