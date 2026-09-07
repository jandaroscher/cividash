import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useTenantStore } from '@/stores/tenant';

describe('tenantStore', () => {
    afterEach(() => {
        delete window.__TENANT__;
    });

    it('reads slug from window.__TENANT__', () => {
        window.__TENANT__ = { slug: 'demo-city', name: 'Demo City' };
        setActivePinia(createPinia());
        const store = useTenantStore();
        expect(store.slug).toBe('demo-city');
        expect(store.name).toBe('Demo City');
    });

    it('falls back to default when window.__TENANT__ is missing', () => {
        delete window.__TENANT__;
        setActivePinia(createPinia());
        const store = useTenantStore();
        expect(store.slug).toBe('default');
        expect(store.name).toBeNull();
    });
});
