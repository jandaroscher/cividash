<template>
    <div>
        <!-- Tiles grid -->
        <main class="overflow-x-hidden pt-7 md:pt-12">
            <div class="container">
                <div v-if="tilesStore.loading" class="text-center py-10">
                    Loading tiles…
                </div>
                <div v-else-if="tilesStore.error" class="text-accent-dark bg-red-50 border border-red-200 rounded-lg p-4">
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
            </div>
        </main>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import TileCard from './components/TileCard.vue';
import { useBrandingStore } from './stores/branding';
import { useTilesStore } from './stores/tiles';

const branding = useBrandingStore();
const tilesStore = useTilesStore();

// fetch tiles once the component mounts
// Note: branding.fetch() is already called in app.js before mounting
onMounted(() => {
    tilesStore.fetchAll();
});
</script>

<style scoped>
.text-primary-color {
    color: var(--primary-color);
}
</style>

