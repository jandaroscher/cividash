<template>
  <main class="home-page">
    <PageView
      v-if="pageData"
      :page-data="pageData"
      :locale="locale"
    />
    <div
      v-else-if="loading"
      class="container text-center py-10"
    >
      <p>{{ messages.loading }}</p>
    </div>
    <div
      v-else-if="error"
      class="container py-10"
    >
      <div
        class="bg-red-50 border border-red-200 rounded-lg p-4"
        role="alert"
      >
        <p class="text-red-800">
          {{ messages.error }}
          {{ error.message || error }}
        </p>
      </div>
    </div>
  </main>
</template>

<script setup>
import { logError } from '../../lib/log.js';
import { ref, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import PageView from './PageView.vue';
import { usePagesStore } from '../../stores/pages';

const route = useRoute();
const pagesStore = usePagesStore();

// Get locale from route meta or default to 'de'
const locale = computed(() => route.meta?.locale || 'de');

const pageData = ref(null);
const loading = ref(false);
const error = ref(null);

// Centralized locale strings
const messages = computed(() => ({
    loading: locale.value === 'en' ? 'Loading homepage…' : 'Lade Startseite…',
    error: locale.value === 'en' ? 'Error loading homepage:' : 'Fehler beim Laden der Startseite:',
    noContent: locale.value === 'en' ? 'No content available' : 'Kein Inhalt verfügbar',
}));

async function loadPage() {
    loading.value = true;
    error.value = null;
    
    try {
        const data = await pagesStore.fetchRootPage(locale.value, true);
        if (data) {
            pageData.value = data;
        } else if (pagesStore.error) {
            error.value = pagesStore.error;
        } else {
            error.value = { message: messages.value.noContent };
        }
    } catch (err) {
        error.value = err;
        logError('Failed to load homepage:', err);
    } finally {
        loading.value = false;
    }
}

// Load page on mount and reload when locale changes
watch(
    () => locale.value,
    () => {
        loadPage();
    },
    { immediate: true }
);
</script>

<style scoped>
.home-page {
    min-height: 50vh;
}
</style>
