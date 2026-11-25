// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import ShareButton from '@/components/overlay/parts/ShareButton.vue';

const locale = { value: 'de' };
vi.mock('@/composables/useLocale', () => ({
    useLocale: () => ({ currentLocale: locale }),
}));

describe('ShareButton', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        locale.value = 'de';
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('opens the native share sheet with the tile URL', async () => {
        const share = vi.fn().mockResolvedValue();
        vi.stubGlobal('navigator', { share });
        const wrapper = mount(ShareButton, { props: { tileSlug: 'co2', tileTitle: 'CO2' } });

        await wrapper.find('button[aria-label="Kachel teilen"]').trigger('click');

        expect(share).toHaveBeenCalledWith({ title: 'CO2', url: `${window.location.origin}/tiles/co2` });
    });

    it('copies the English tile URL when native sharing is missing', async () => {
        locale.value = 'en';
        const writeText = vi.fn().mockResolvedValue();
        vi.stubGlobal('navigator', { clipboard: { writeText } });
        const wrapper = mount(ShareButton, { props: { tileSlug: 'co2-en' } });

        await wrapper.find('button[aria-label="Share tile"]').trigger('click');
        await flushPromises();

        expect(writeText).toHaveBeenCalledWith(`${window.location.origin}/en/tiles/co2-en`);
        expect(wrapper.text()).toContain('Link copied');
    });

    it('does not copy when the user cancels the share sheet', async () => {
        const writeText = vi.fn();
        vi.stubGlobal('navigator', {
            share: vi.fn().mockRejectedValue(Object.assign(new Error('cancel'), { name: 'AbortError' })),
            clipboard: { writeText },
        });
        const wrapper = mount(ShareButton, { props: { tileSlug: 'co2' } });

        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(writeText).not.toHaveBeenCalled();
    });
});
