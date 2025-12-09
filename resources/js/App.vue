<template>
    <div class="app-wrapper">
        <Header />
        <main class="overflow-x-hidden pt-7 md:pt-12">
            <RouterView />
        </main>
        <Footer />
    </div>
</template>

<script setup>
import { RouterView } from 'vue-router';
import Header from './components/layout/Header.vue';
import Footer from './components/layout/Footer.vue';
// Stores are initialized in app.js before mounting
</script>

<style scoped>
.app-wrapper {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

main {
    flex: 1;
}
</style>
