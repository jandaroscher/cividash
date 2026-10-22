import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { ref } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import TileCard from '@/components/TileCard.vue';

const locale = ref('de');

vi.mock('@/composables/useLocale', () => ({
    useLocale: () => ({ currentLocale: locale }),
}));

vi.mock('@/composables/useHelpContext', () => ({
    useHelpContext: () => ({ getTooltip: () => null }),
}));

function metric(values, overrides = {}) {
    return {
        metric_key: 'co2',
        label: { de: 'CO₂-Ausstoß', en: 'CO₂ emissions' },
        unit: { de: 'Tonnen', en: 'tonnes' },
        indicator_type: 'small',
        values: Object.entries(values).map(([period_key, value]) => ({ period_key, value })),
        ...overrides,
    };
}

// TileCard builds its indicators on mount, so wait for that render.
async function mountTile(...metricDefinitions) {
    const wrapper = mount(TileCard, {
        props: {
            tile: { id: 1, title: 'Klima', metric_definitions: metricDefinitions, categories: [], background_blocks: [] },
        },
        global: {
            stubs: { Tooltip: { template: '<div><slot /></div>' } },
            directives: { 'intersection-observer': {} },
        },
    });
    await flushPromises();
    return wrapper;
}

async function selectPeriod(wrapper, period) {
    // The slider itself is stubbed in tests/js/setup.js; emit what dragging it emits.
    wrapper.findComponent('.vue-slider-stub').vm.$emit('update:modelValue', period);
    await wrapper.vm.$nextTick();
}

function visibleArrow(wrapper) {
    const arrow = wrapper.findAll('img[src*="arrow-"]').find((img) => img.element.style.display !== 'none');
    return arrow?.attributes('src').match(/arrow-(\w+)\.svg/)[1] ?? null;
}

describe('TileCard indicators', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        locale.value = 'de';
    });

    it('shows the latest period with its formatted value and unit', async () => {
        const wrapper = await mountTile(metric({ 2022: 1200, 2023: 12345.5 }));

        expect(wrapper.text()).toContain('CO₂-Ausstoß');
        expect(wrapper.text()).toContain('12.345,5 Tonnen');
        expect(wrapper.text()).toContain('2023');
    });

    it('shows the value of the period picked on the year slider', async () => {
        const wrapper = await mountTile(metric({ 2022: 1200, 2023: 1500 }));

        await selectPeriod(wrapper, '2022');

        expect(wrapper.text()).toContain('1.200 Tonnen');
        expect(wrapper.text()).not.toContain('1.500');
    });

    it('points the trend arrow in the direction of the change to the previous period', async () => {
        const wrapper = await mountTile(metric({ 2021: 900, 2022: 1200, 2023: 800 }));
        expect(visibleArrow(wrapper)).toBe('down');

        await selectPeriod(wrapper, '2022');
        expect(visibleArrow(wrapper)).toBe('up');

        await selectPeriod(wrapper, '2021');
        expect(visibleArrow(wrapper)).toBeNull();
    });

    it('shows a dash for a period without a value', async () => {
        const wrapper = await mountTile(metric({ 2023: 1500 }), metric({ 2022: 7 }, { metric_key: 'trees' }));

        expect(wrapper.text()).toContain('1.500 Tonnen');
        expect(wrapper.findAll('.font-bold').some((el) => el.text() === '-')).toBe(true);
    });

    it('shows a measured zero as 0, not as a missing value', async () => {
        const wrapper = await mountTile(metric({ 2023: 0 }));

        expect(wrapper.find('.text-theme-h5').text()).toBe('0 Tonnen');
    });

    it('renders a big indicator with the same value', async () => {
        const wrapper = await mountTile(metric({ 2023: 42 }, { indicator_type: 'big' }));

        expect(wrapper.text()).toContain('42 Tonnen');
    });

    it('shows title and unit in English for English visitors', async () => {
        locale.value = 'en';
        const wrapper = await mountTile(metric({ 2023: 12345.5 }));

        expect(wrapper.text()).toContain('CO₂ emissions');
        expect(wrapper.text()).toContain('12,345.5 tonnes');
    });
});
