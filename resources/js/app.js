import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import { useBrandingStore } from './stores/branding';

const app = createApp(App);
const pinia = createPinia();
app.use(pinia);

// Fetch branding before mounting
const branding = useBrandingStore();
branding.fetch()
    .then(() => {
        app.mount('#app');
    })
    .catch((error) => {
        console.error('Failed to fetch branding settings, mounting app anyway:', error);
        app.mount('#app');
    });
