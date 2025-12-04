import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import { useBrandingStore } from './stores/branding';
import { useHelpStore } from './stores/help';
import { vIntersectionObserver } from '@vueuse/components';
// Import dotlottie-player to register the Web Component
import '@johanaarstein/dotlottie-player';

const app = createApp(App);
const pinia = createPinia();
app.use(pinia);
app.directive('intersection-observer', vIntersectionObserver);

// Fetch branding and help content before mounting
const branding = useBrandingStore();
const helpStore = useHelpStore();

Promise.all([
    branding.fetch(),
    helpStore.loadHelpContent(),
])
    .then(() => {
        app.mount('#app');
    })
    .catch((error) => {
        logError('Failed to fetch settings, mounting app anyway:', error);
        app.mount('#app');
    });
