<template>
    <main role="main" aria-labelledby="not-found-title">
        <h1 id="not-found-title">404 - {{ messages.title }}</h1>
        <p>{{ messages.description }}</p>
        <RouterLink to="/" class="inline-block mt-4 text-accent hover:text-accent-dark transition-colors duration-200">
            {{ messages.homeLink }}
        </RouterLink>
    </main>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useLocale } from '../../composables/useLocale';

const route = useRoute();
const { currentLocale } = useLocale();

const locale = computed(() => route.meta?.locale || currentLocale.value);

const messages = computed(() => ({
    title: locale.value === 'en' ? 'Page Not Found' : 'Seite nicht gefunden',
    description: locale.value === 'en' 
        ? 'The page you are looking for does not exist.' 
        : 'Die gesuchte Seite existiert nicht.',
    homeLink: locale.value === 'en' ? 'Go to Home' : 'Zur Startseite',
}));
</script>

