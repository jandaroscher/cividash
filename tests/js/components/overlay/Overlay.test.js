// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import Overlay from '@/components/overlay/Overlay.vue';
import { useOverlayStore } from '@/stores/overlay';

vi.mock('@/composables/useLocale', () => ({
    useLocale: () => ({ currentLocale: { value: 'de' } }),
}));

describe('Overlay', () => {
    let wrapper;

    beforeEach(async () => {
        setActivePinia(createPinia());
        useOverlayStore().openOverlay({ id: 7, title: 'Radwege' });
        wrapper = mount(Overlay, {
            attachTo: document.body,
            global: {
                stubs: {
                    teleport: true,
                    OverlayHeader: { template: '<header><button id="first">Schließen</button></header>' },
                    OverlayContent: { template: '<div><a id="last" href="/radwege">Mehr</a></div>' },
                },
            },
        });
    });

    afterEach(() => wrapper.unmount());

    it('closes on Escape', async () => {
        await wrapper.find('aside').trigger('keydown', { key: 'Escape' });

        expect(useOverlayStore().open).toBe(false);
    });

    it('is announced as a modal dialog named after the tile', () => {
        const dialog = wrapper.find('aside');

        expect(dialog.attributes()).toMatchObject({ role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Radwege' });
    });

    it('takes the closed overlay out of the tab order', async () => {
        useOverlayStore().closeOverlay();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('aside').attributes('inert')).toBeDefined();
        expect(wrapper.find('aside').attributes('aria-hidden')).toBe('true');
    });

    it('keeps keyboard focus inside the open overlay', () => {
        document.getElementById('last').focus();

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true }));

        expect(document.activeElement.id).toBe('first');
    });
});
