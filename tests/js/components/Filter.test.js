import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import Filter from '@/components/Filter.vue';
import { useFilterStore } from '@/stores/filter';
import { useBrandingStore } from '@/stores/branding';
import { __localeState } from '@/composables/useLocale';

// Mock composables that use vue-router internally. currentLocale is a real
// vue ref (as the actual composable returns), created via a dynamic import
// inside the factory to sidestep hoisting order - so <script setup>'s
// automatic template unwrapping is exercised the same way it is in
// production. A plain { value } object would let a broken
// `currentLocale === 'en'` template comparison silently read as if it were
// `.value`-correct.
vi.mock('@/composables/useLocale', async () => {
    const { ref } = await import('vue');
    const localeState = ref('de');
    return {
        useLocale: () => ({
            currentLocale: localeState,
            setLocale: vi.fn(),
            getTranslatedSlug: vi.fn(),
            supportedLocales: ['de', 'en'],
            defaultLocale: 'de',
            getLocale: () => localeState.value,
        }),
        __localeState: localeState,
    };
});

vi.mock('@/composables/useHelpContext', () => ({
    useHelpContext: () => ({
        getTooltip: vi.fn(() => null),
        currentContext: { value: null },
        helpOverlayOpen: { value: false },
    }),
}));

describe('Filter', () => {
    let filterStore;
    let fetchMock;

    beforeEach(() => {
        setActivePinia(createPinia());
        filterStore = useFilterStore();
        useBrandingStore();
        __localeState.value = 'de';

        // Reset URL to avoid state leaking between tests
        window.history.replaceState({}, '', '/');

        // Mock global fetch for the filter API call made on mount
        fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: () =>
                Promise.resolve({
                    data: {
                        labels: { header: 'Filter' },
                        groups: [
                            {
                                id: 1,
                                key: 'dimensions',
                                title: { de: 'Dimensionen', en: 'Dimensions' },
                                items: [
                                    { id: 10, key: 'green', title: { de: 'Gruen', en: 'Green' } },
                                    { id: 11, key: 'social', title: { de: 'Sozial', en: 'Social' } },
                                ],
                            },
                            {
                                id: 2,
                                key: 'sdg',
                                title: { de: 'SDG-Ziele', en: 'SDG Goals' },
                                items: [
                                    { id: 20, key: 'sdg-1', title: { de: 'Keine Armut', en: 'No Poverty' } },
                                ],
                            },
                        ],
                    },
                }),
        });
        globalThis.fetch = fetchMock;
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    function createWrapper(props = {}) {
        return shallowMount(Filter, {
            props: {
                showSearch: true,
                showFilter: true,
                ...props,
            },
            global: {
                stubs: {
                    Tooltip: { template: '<div class="tooltip-stub"><slot /></div>', props: ['text'] },
                    FilterGroup: { template: '<div class="filter-group-stub"></div>', props: ['group'] },
                },
            },
        });
    }

    it('renders the filter header when heading prop is set', async () => {
        const wrapper = createWrapper({ heading: 'Filter' });
        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('h2').text()).toBe('Filter');
    });

    it('fetches filter groups on mount and renders group buttons', async () => {
        const wrapper = createWrapper();
        await vi.dynamicImportSettled();
        // Wait for the fetch to resolve and DOM to update
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(fetchMock).toHaveBeenCalledWith(
            expect.stringContaining('/api/filters?locale=de')
        );

        const buttons = wrapper.findAll('[role="tab"]');
        expect(buttons.length).toBe(2);
        expect(buttons[0].text()).toBe('Dimensionen');
        expect(buttons[1].text()).toBe('SDG-Ziele');
    });

    it('changes level1 filter when a group button is clicked', async () => {
        const wrapper = createWrapper();
        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const buttons = wrapper.findAll('[role="tab"]');
        // Click the second button (SDG)
        await buttons[1].trigger('click');

        expect(filterStore.level1Filter).toBe('sdg');
    });

    it('renders search input when showSearch is true', async () => {
        const wrapper = createWrapper({ showSearch: true });
        await wrapper.vm.$nextTick();

        const searchInput = wrapper.find('input[type="text"]');
        expect(searchInput.exists()).toBe(true);
        expect(searchInput.attributes('aria-label')).toBe('Kacheln durchsuchen');
    });

    it('hides search input when showSearch is false', async () => {
        const wrapper = createWrapper({ showSearch: false });
        await wrapper.vm.$nextTick();

        const searchInput = wrapper.find('input[type="text"]');
        expect(searchInput.exists()).toBe(false);
    });

    it('hides filter buttons when showFilter is false', async () => {
        const wrapper = createWrapper({ showFilter: false });
        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const buttons = wrapper.findAll('[role="tab"]');
        expect(buttons.length).toBe(0);
    });

    it('marks the active filter button with aria-selected true', async () => {
        const wrapper = createWrapper();
        // Wait for the async fetch to resolve and for the store to be updated
        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // First group should be selected by default (set by setGroups -> defaultGroupKey)
        // The store's setGroups sets level1Filter to the first group's key ('dimensions')
        expect(filterStore.level1Filter).toBe('dimensions');

        const buttons = wrapper.findAll('[role="tab"]');
        expect(buttons.length).toBe(2);
        expect(buttons[0].attributes('aria-selected')).toBe('true');
        expect(buttons[1].attributes('aria-selected')).toBe('false');
    });

    it('updates search query in filter store with debounce', async () => {
        vi.useFakeTimers();
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const searchInput = wrapper.find('input[type="text"]');
        await searchInput.setValue('Klima');
        await searchInput.trigger('input');

        // Before debounce timeout, the store should not be updated
        expect(filterStore.searchQuery).toBe('');

        // Advance timers by the debounce period (300ms)
        vi.advanceTimersByTime(300);
        await wrapper.vm.$nextTick();

        expect(filterStore.searchQuery).toBe('Klima');

        vi.useRealTimers();
    });

    it('only marks the active tab with the active class, not other tabs (active !== hover)', async () => {
        const wrapper = createWrapper();
        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const buttons = wrapper.findAll('[role="tab"]');
        expect(buttons[0].classes()).toContain('filter-button--active');
        expect(buttons[1].classes()).not.toContain('filter-button--active');
    });

    it('clears the search field, resets the store, and returns focus to the input', async () => {
        vi.useFakeTimers();
        const wrapper = createWrapper({ showSearch: true });
        await wrapper.vm.$nextTick();

        const searchInput = wrapper.find('input[type="text"]');
        await searchInput.setValue('Klima');
        await searchInput.trigger('input');
        vi.advanceTimersByTime(300);
        await wrapper.vm.$nextTick();

        const focusSpy = vi.spyOn(searchInput.element, 'focus');
        const clearButton = wrapper.find('.search-clear-button');
        await clearButton.trigger('click');

        expect(searchInput.element.value).toBe('');
        expect(filterStore.searchQuery).toBe('');
        expect(focusSpy).toHaveBeenCalled();

        vi.useRealTimers();
    });

    it('sets the clear button aria-label per locale (de/en)', async () => {
        const deWrapper = createWrapper({ showSearch: true });
        await deWrapper.vm.$nextTick();
        expect(deWrapper.find('.search-clear-button').attributes('aria-label')).toBe('Suche leeren');

        __localeState.value = 'en';
        const enWrapper = createWrapper({ showSearch: true });
        await enWrapper.vm.$nextTick();
        expect(enWrapper.find('.search-clear-button').attributes('aria-label')).toBe('Clear search');
    });
});
