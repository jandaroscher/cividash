<template>
    <div class="max-w-[363px] block hyphens-auto shadow-card">
        <!-- Colored header pane -->
        <div :class="[backgroundClass]" class="py-6 px-4 text-black relative">
            <img
                v-if="tile.icon"
                :src="tile.icon"
                :alt="`${localizedTitle} icon`"
                class="w-8 h-8 absolute top-4 right-4"
            />
            <div class="text-3xl font-bold mb-4 hyphens-auto px-4">
                {{ localizedTitle }}
            </div>
        </div>

        <!-- White content pane -->
        <div class="bg-white py-6 px-4">
            <p class="text-sm text-gray-600 hyphens-auto">
                {{ excerpt }}
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    tile: {
        type: Object,
        required: true,
    },
});

const currentLocale = computed(() =>
    navigator.language.startsWith('en') ? 'en' : 'de'
);

const localizedTitle = computed(() => {
    return (
        props.tile.title?.[currentLocale.value] ||
        props.tile.title?.de ||
        ''
    );
});

const rawDescription = computed(() => {
    return (
        props.tile.description?.[currentLocale.value] ||
        props.tile.description?.de ||
        ''
    );
});

const excerpt = computed(() => {
    const text = rawDescription.value.replace(/<[^>]+>/g, '');
    return text.length > 100 ? text.slice(0, 100) + '…' : text;
});

const backgroundClass = computed(() => {
    const dim = props.tile.categories?.[0]?.slug;
    switch (dim) {
        case 'gerecht':
            return 'bg-orange-100';
        case 'produktiv':
            return 'bg-blue-100';
        case 'gruen':
        case 'green':
            return 'bg-green-100';
        default:
            return 'bg-gray-100';
    }
});
</script>

<style scoped>

</style>

