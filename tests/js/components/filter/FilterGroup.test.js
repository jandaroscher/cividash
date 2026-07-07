import { mount, flushPromises } from '@vue/test-utils';
import { setActivePinia, createPinia } from 'pinia';
import { ref } from 'vue';
import { describe, it, expect, beforeEach, vi } from 'vitest';

// Locale composable is mocked so the component does not depend on app bootstrap.
vi.mock('@/composables/useLocale', () => ({
    useLocale: () => ({ currentLocale: ref('de') }),
}));

// Flicking is a carousel wrapper; stub it to just render its default slot so the
// item markup (and its <img>) is present in the DOM for the test.
vi.mock('@egjs/vue3-flicking', () => ({
    default: {
        name: 'Flicking',
        template: '<div class="flicking-stub"><slot /></div>',
    },
}));
vi.mock('@egjs/vue3-flicking/dist/flicking.css', () => ({}));
vi.mock('@egjs/flicking-plugins', () => ({ Pagination: class {}, Arrow: class {} }));
vi.mock('@egjs/flicking-plugins/dist/arrow.css', () => ({}));
vi.mock('@egjs/flicking-plugins/dist/pagination.css', () => ({}));

import FilterGroup from '@/components/filter/FilterGroup.vue';

async function mountGroup() {
    const wrapper = mount(FilterGroup, {
        props: {
            group: {
                id: 7,
                items: [
                    { id: 1, key: 'mobil', title: { de: 'Mobilität' }, icon: '/storage/seeds/handlungsfelder/mobilitaet_infrastruktur.svg' },
                ],
            },
        },
    });
    // onMounted awaits a tick before flipping `isReady` to render Flicking's slot.
    await flushPromises();
    return wrapper;
}

describe('FilterGroup icon fallback', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the category icon initially', async () => {
        const wrapper = await mountGroup();
        const img = wrapper.find('img');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe('/storage/seeds/handlungsfelder/mobilitaet_infrastruktur.svg');
    });

    it('hides the icon (no broken alt text) once it fails to load', async () => {
        const wrapper = await mountGroup();
        // Trigger the native <img> error event; the handler must key off the raw
        // src attribute, not the resolved absolute .src IDL property.
        await wrapper.find('img').trigger('error');
        expect(wrapper.find('img').exists()).toBe(false);
    });
});
