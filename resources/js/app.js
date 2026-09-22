import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createHead } from '@unhead/vue/client';
import App from './App.vue';
import router from './router';
import { useBrandingStore } from './stores/branding';
import { useHelpStore } from './stores/help';
import { useHeaderStore } from './stores/header';
import { useFooterStore } from './stores/footer';
import { resolveLocale } from './utils/locale';
import { vIntersectionObserver } from '@vueuse/components';
// Import dotlottie-wc to register the Web Component
import './lib/dotlottie';
import { logError } from './lib/log.js';

const app = createApp(App);
const pinia = createPinia();
const head = createHead();
app.use(pinia);
app.use(head);
app.directive('intersection-observer', vIntersectionObserver);

// Fetch branding, help, header and footer content before mounting.
// Router is installed AFTER stores are populated so that router guards
// (e.g. the /en redirect when English is disabled) see the real API values
// instead of default store state during initial navigation.
const branding = useBrandingStore();
const helpStore = useHelpStore();
const headerStore = useHeaderStore();
const footerStore = useFooterStore();

const defaultLocale = resolveLocale();

Promise.all([
    branding.fetch(),
    helpStore.loadHelpContent(),
    headerStore.fetchConfig(defaultLocale),
    footerStore.fetchConfig(defaultLocale),
])
    .then(() => {
        app.use(router);
        app.mount('#app');
    })
    .catch((error) => {
        logError('Failed to fetch settings, mounting app anyway:', error);
        app.use(router);
        app.mount('#app');
    });
