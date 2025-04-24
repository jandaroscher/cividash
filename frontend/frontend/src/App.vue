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

        <!-- Dashboard content goes here -->
    </div>
</template>

<script setup>
import { useBrandingStore } from './stores/branding';
const branding = useBrandingStore();
</script>

<style scoped>
.text-primary-color {
    color: var(--primary-color);
}
</style>
