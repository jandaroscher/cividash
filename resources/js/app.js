import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { useBrandingStore } from './stores/branding';
import { useHelpStore } from './stores/help';
import { useHeaderStore } from './stores/header';
import { useFooterStore } from './stores/footer';
import { resolveLocale } from './utils/locale';
import { vIntersectionObserver } from '@vueuse/components';
// Import dotlottie-player to register the Web Component
import '@johanaarstein/dotlottie-player';

const app = createApp(App);
const pinia = createPinia();
app.use(pinia);
app.use(router);
app.directive('intersection-observer', vIntersectionObserver);

// Fetch branding, help, header and footer content before mounting
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
        app.mount('#app');
    })
    .catch((error) => {
        logError('Failed to fetch settings, mounting app anyway:', error);
        app.mount('#app');
    });
