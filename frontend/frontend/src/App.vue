<template>
    <div>
        <header class="py-4 flex items-center">
            <img
                v-if="branding.logoUrl"
                :src="branding.logoUrl"
                alt="Logo"
                class="h-12 mr-4"
            />
            <h1 class="text-2xl font-bold text-primary-color">
                Open Source Dashboard
            </h1>
        </header>

        <main class="mt-6">
            <div v-if="tiles.loading">Loading tiles…</div>
            <div v-else-if="tiles.error">
                Error fetching tiles: {{ tiles.error.message }}
            </div>
            <div v-else class="space-y-4">
                <div
                    v-for="tile in tiles.tiles"
                    :key="tile.id"
                    class="p-4 border rounded-lg"
                >
                    <!-- assuming tile.title is an object { de:…, en:… } -->
                    <h2 class="text-xl font-semibold">
                        {{ tile.title[ currentLocale ] || tile.title.de }}
                    </h2>
                    <div
                        v-html="tile.description[currentLocale] || tile.description.de"
                    ></div>
                </div>
            </div>
        </main>
    </div>
</template>

<script setup>
import { onMounted, computed } from 'vue';
import { useBrandingStore } from './stores/branding';
import { useTilesStore }    from './stores/tiles';

// your locale – adjust if you have i18n
const currentLocale = navigator.language.startsWith('en') ? 'en' : 'de';

const branding = useBrandingStore();
const tiles    = useTilesStore();

// fetch tiles once the component mounts
onMounted(() => {
    tiles.fetchAll();
});
</script>

<style scoped>
.text-primary-color {
    color: var(--primary-color);
}
</style>
