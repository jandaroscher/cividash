import { vi } from 'vitest';
import { config } from '@vue/test-utils';

// Mock window.APP_URL for API base URL resolution
window.APP_URL = 'http://localhost';

// Mock modules that require browser APIs not available in happy-dom
vi.mock('@/lib/dotlottie', () => ({}));

vi.mock('vue-slider-component/lib/vue-slider.vue', () => ({
    default: {
        template: '<div class="vue-slider-stub"></div>',
        props: ['data', 'modelValue', 'tooltip', 'dotAttrs'],
    },
}));

vi.mock('vue-slider-component/theme/default.css', () => ({}));

// Global fetch mock helper
export function mockFetch(data, options = {}) {
    const { ok = true, status = 200, headers = {} } = options;
    return vi.fn().mockResolvedValue({
        ok,
        status,
        statusText: ok ? 'OK' : 'Error',
        json: () => Promise.resolve(data),
        headers: new Headers(headers),
    });
}

// Global fetch error mock helper
export function mockFetchError(message = 'Network error') {
    return vi.fn().mockRejectedValue(new Error(message));
}

// Configure Vue Test Utils defaults
config.global.stubs = {
    'router-link': {
        template: '<a :href="to"><slot /></a>',
        props: ['to'],
    },
    'router-view': true,
};
