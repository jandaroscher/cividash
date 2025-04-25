<template>
    <div>
        <header class="py-4 flex items-center">
            <img
                width="200"
                v-if="branding.logoUrl"
                :src="branding.logoUrl"
                alt="Logo"
                class="mr-4"
            />
            <h1 class="text-2xl font-bold text-primary-color">
                Open Source Dashboard
            </h1>
        </header>

        <!-- Tiles grid -->
        <main class="p-6">
            <div v-if="tilesStore.loading" class="text-center py-10">
                Loading tiles…
            </div>
            <div v-else-if="tilesStore.error" class="text-red-600">
                Error loading tiles: {{ tilesStore.error.message }}
            </div>
            <div
                v-else
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"
            >
                <TileCard
                    v-for="tile in tilesStore.tiles"
                    :key="tile.id"
                    :tile="tile"
                />
            </div>
        </main>
    </div>
</template>

<script setup>
import { onMounted, computed } from 'vue';
import TileCard from './components/TileCard.vue';
import { useBrandingStore } from './stores/branding';
import { useTilesStore }    from './stores/tiles';

// your locale – adjust if you have i18n
const currentLocale = navigator.language.startsWith('en') ? 'en' : 'de';

const branding = useBrandingStore();
const tilesStore = useTilesStore();

// fetch tiles once the component mounts
onMounted(() => {
    branding.fetch();
    tilesStore.fetchAll();
});
</script>

<style scoped>
.text-primary-color {
    color: var(--primary-color);
}
</style>
