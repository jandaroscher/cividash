<template>
  <RouterView v-if="isEmbed" />
  <div
    v-else
    class="app-wrapper"
  >
    <Header />
    <main class="overflow-x-hidden pt-7 md:pt-12">
      <RouterView />
    </main>
    <Footer />
    <ScrollToTop />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterView, useRoute } from 'vue-router';
import Header from './components/layout/Header.vue';
import Footer from './components/layout/Footer.vue';
import ScrollToTop from './components/ScrollToTop.vue';
// Stores are initialized in app.js before mounting

const route = useRoute();
// The initial navigation is still pending on first render, so fall back to the
// URL; otherwise embeds would flash the dashboard header inside the iframe.
const isEmbed = computed(() =>
    route.matched.length
        ? Boolean(route.meta.embed)
        : /^\/(en\/)?embed\//.test(window.location.pathname),
);
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
