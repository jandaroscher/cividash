import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useFilterStore } from '@/stores/filter';

describe('filterStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.restoreAllMocks();
        // Mock window.history.replaceState to prevent errors
        vi.spyOn(window.history, 'replaceState').mockImplementation(() => {});
    });

    describe('initial state', () => {
        it('has correct default values', () => {
            const store = useFilterStore();
            expect(store.level1Filter).toBeNull();
            expect(store.level2Filter).toEqual({ title: null, key: null });
            expect(store.searchQuery).toBe('');
            expect(store.groups).toEqual([]);
            expect(store.loading).toEqual({ groups: false });
            expect(store.error).toBeNull();
        });
    });

    describe('defaultGroupKey getter', () => {
        it('returns first group key when groups exist', () => {
            const store = useFilterStore();
            store.groups = [
                { key: 'dimensions', title: 'Dimensions' },
                { key: 'sdg', title: 'SDG Goals' },
            ];
            expect(store.defaultGroupKey).toBe('dimensions');
        });

        it('returns null for empty groups', () => {
            const store = useFilterStore();
            store.groups = [];
            expect(store.defaultGroupKey).toBeNull();
        });

        it('returns null when groups is not an array', () => {
            const store = useFilterStore();
            store.groups = null;
            expect(store.defaultGroupKey).toBeNull();
        });
    });

    describe('setLevel1Filter', () => {
        it('sets level1Filter and clears level2Filter', () => {
            const store = useFilterStore();
            store.level2Filter = { title: 'Something', key: 'something' };

            store.setLevel1Filter('dimensions');

            expect(store.level1Filter).toBe('dimensions');
            expect(store.level2Filter).toEqual({ title: null, key: null });
        });

        it('calls syncToUrl after setting filter', () => {
            const store = useFilterStore();
            store.groups = [{ key: 'dimensions' }, { key: 'sdg' }];
            store.level1Filter = 'dimensions';

            store.setLevel1Filter('sdg');

            expect(window.history.replaceState).toHaveBeenCalled();
        });
    });

    describe('setLevel2Filter', () => {
        it('sets level2Filter', () => {
            const store = useFilterStore();
            const filter = { title: 'Economy', key: 'economy' };

            store.setLevel2Filter(filter);

            expect(store.level2Filter).toEqual(filter);
        });

        it('calls syncToUrl after setting filter', () => {
            const store = useFilterStore();
            store.setLevel2Filter({ title: 'Test', key: 'test' });

            expect(window.history.replaceState).toHaveBeenCalled();
        });
    });

    describe('clearLevel2Filter', () => {
        it('resets level2Filter to default', () => {
            const store = useFilterStore();
            store.level2Filter = { title: 'Something', key: 'something' };

            store.clearLevel2Filter();

            expect(store.level2Filter).toEqual({ title: null, key: null });
        });

        it('calls syncToUrl', () => {
            const store = useFilterStore();
            store.clearLevel2Filter();

            expect(window.history.replaceState).toHaveBeenCalled();
        });
    });

    describe('setSearchQuery', () => {
        it('sets search query', () => {
            const store = useFilterStore();
            store.setSearchQuery('energy');

            expect(store.searchQuery).toBe('energy');
        });

        it('sets empty string when falsy value provided', () => {
            const store = useFilterStore();
            store.setSearchQuery('something');
            store.setSearchQuery(null);

            expect(store.searchQuery).toBe('');
        });

        it('sets empty string for undefined', () => {
            const store = useFilterStore();
            store.setSearchQuery(undefined);

            expect(store.searchQuery).toBe('');
        });

        it('calls syncToUrl', () => {
            const store = useFilterStore();
            store.setSearchQuery('test');

            expect(window.history.replaceState).toHaveBeenCalled();
        });
    });

    describe('clearFilters', () => {
        it('resets all filters to defaults', () => {
            const store = useFilterStore();
            store.groups = [{ key: 'dimensions' }, { key: 'sdg' }];
            store.level1Filter = 'sdg';
            store.level2Filter = { title: 'Goal 1', key: '1' };
            store.searchQuery = 'energy';

            store.clearFilters();

            expect(store.level1Filter).toBe('dimensions'); // defaultGroupKey
            expect(store.level2Filter).toEqual({ title: null, key: null });
            expect(store.searchQuery).toBe('');
        });

        it('sets level1Filter to null when no groups exist', () => {
            const store = useFilterStore();
            store.level1Filter = 'something';
            store.searchQuery = 'test';

            store.clearFilters();

            expect(store.level1Filter).toBeNull(); // defaultGroupKey is null
            expect(store.searchQuery).toBe('');
        });

        it('calls syncToUrl', () => {
            const store = useFilterStore();
            store.clearFilters();

            expect(window.history.replaceState).toHaveBeenCalled();
        });
    });

    describe('setGroups', () => {
        it('sets groups array', () => {
            const store = useFilterStore();
            const groups = [
                { key: 'dimensions', title: 'Dimensions', items: [] },
                { key: 'sdg', title: 'SDG Goals', items: [] },
            ];

            store.setGroups(groups);

            expect(store.groups).toEqual(groups);
        });

        it('sets level1Filter to defaultGroupKey when current filter is null', () => {
            const store = useFilterStore();
            const groups = [
                { key: 'dimensions', title: 'Dimensions' },
                { key: 'sdg', title: 'SDG Goals' },
            ];

            store.setGroups(groups);

            expect(store.level1Filter).toBe('dimensions');
        });

        it('sets level1Filter to defaultGroupKey when current filter is invalid', () => {
            const store = useFilterStore();
            store.level1Filter = 'nonexistent';

            const groups = [
                { key: 'dimensions', title: 'Dimensions' },
                { key: 'sdg', title: 'SDG Goals' },
            ];

            store.setGroups(groups);

            expect(store.level1Filter).toBe('dimensions');
        });

        it('preserves level1Filter when it matches a valid group key', () => {
            const store = useFilterStore();
            store.level1Filter = 'sdg';

            const groups = [
                { key: 'dimensions', title: 'Dimensions' },
                { key: 'sdg', title: 'SDG Goals' },
            ];

            store.setGroups(groups);

            expect(store.level1Filter).toBe('sdg');
        });

        it('clears level2Filter when level1Filter is reset', () => {
            const store = useFilterStore();
            store.level1Filter = 'nonexistent';
            store.level2Filter = { title: 'Some Filter', key: 'some-key' };

            const groups = [{ key: 'dimensions', title: 'Dimensions' }];
            store.setGroups(groups);

            expect(store.level2Filter).toEqual({ title: null, key: null });
        });

        it('handles non-array input by setting empty array', () => {
            const store = useFilterStore();
            store.setGroups('not an array');

            expect(store.groups).toEqual([]);
        });

        it('handles null input', () => {
            const store = useFilterStore();
            store.setGroups(null);

            expect(store.groups).toEqual([]);
        });
    });

    describe('setLoading', () => {
        it('sets loading.groups', () => {
            const store = useFilterStore();
            store.setLoading(true);

            expect(store.loading.groups).toBe(true);

            store.setLoading(false);
            expect(store.loading.groups).toBe(false);
        });
    });

    describe('setError', () => {
        it('sets error', () => {
            const store = useFilterStore();
            const error = new Error('Something went wrong');
            store.setError(error);

            expect(store.error).toBe(error);
        });

        it('clears error with null', () => {
            const store = useFilterStore();
            store.setError(new Error('err'));
            store.setError(null);

            expect(store.error).toBeNull();
        });
    });

    describe('restoreFromUrl', () => {
        it('restores level1Filter from URL params', () => {
            // Mock window.location
            Object.defineProperty(window, 'location', {
                value: { href: 'http://localhost/tiles?filter=sdg', search: '?filter=sdg' },
                writable: true,
            });

            const store = useFilterStore();
            store.groups = [
                { key: 'dimensions', title: 'Dimensions' },
                { key: 'sdg', title: 'SDG Goals' },
            ];
            store.restoreFromUrl();

            expect(store.level1Filter).toBe('sdg');
        });

        it('restores searchQuery from URL params', () => {
            Object.defineProperty(window, 'location', {
                value: { href: 'http://localhost/tiles?search=energy', search: '?search=energy' },
                writable: true,
            });

            const store = useFilterStore();
            store.groups = [{ key: 'dimensions', title: 'Dimensions' }];
            store.restoreFromUrl();

            expect(store.searchQuery).toBe('energy');
        });

        it('restores level2Filter from URL params', () => {
            Object.defineProperty(window, 'location', {
                value: {
                    href: 'http://localhost/tiles?filter=dimensions&filterKey=economy',
                    search: '?filter=dimensions&filterKey=economy',
                },
                writable: true,
            });

            const store = useFilterStore();
            store.groups = [
                {
                    key: 'dimensions',
                    title: 'Dimensions',
                    items: [{ key: 'economy', title: 'Economy' }],
                },
            ];
            store.restoreFromUrl();

            expect(store.level1Filter).toBe('dimensions');
            expect(store.level2Filter.key).toBe('economy');
            expect(store.level2Filter.title).toBe('Economy');
        });

        it('defaults to defaultGroupKey when no URL params', () => {
            Object.defineProperty(window, 'location', {
                value: { href: 'http://localhost/tiles', search: '' },
                writable: true,
            });

            const store = useFilterStore();
            store.groups = [{ key: 'dimensions', title: 'Dimensions' }];
            store.restoreFromUrl();

            expect(store.level1Filter).toBe('dimensions');
            expect(store.level2Filter).toEqual({ title: null, key: null });
            expect(store.searchQuery).toBe('');
        });

        it('falls back to defaultGroupKey when filter param is invalid group', () => {
            Object.defineProperty(window, 'location', {
                value: {
                    href: 'http://localhost/tiles?filter=nonexistent',
                    search: '?filter=nonexistent',
                },
                writable: true,
            });

            const store = useFilterStore();
            store.groups = [{ key: 'dimensions', title: 'Dimensions' }];
            store.restoreFromUrl();

            expect(store.level1Filter).toBe('dimensions');
            expect(store.level2Filter).toEqual({ title: null, key: null });
        });

        it('restores all params together', () => {
            Object.defineProperty(window, 'location', {
                value: {
                    href: 'http://localhost/tiles?filter=sdg&filterKey=1&search=climate',
                    search: '?filter=sdg&filterKey=1&search=climate',
                },
                writable: true,
            });

            const store = useFilterStore();
            store.groups = [
                { key: 'dimensions', title: 'Dimensions', items: [] },
                {
                    key: 'sdg',
                    title: 'SDG Goals',
                    items: [{ id: 1, key: '1', title: 'No Poverty' }],
                },
            ];
            store.restoreFromUrl();

            expect(store.level1Filter).toBe('sdg');
            expect(store.level2Filter.key).toBe('1');
            expect(store.searchQuery).toBe('climate');
        });
    });

    describe('syncToUrl', () => {
        it('updates URL with filter parameter when level1Filter differs from default', () => {
            Object.defineProperty(window, 'location', {
                value: { href: 'http://localhost/tiles', search: '' },
                writable: true,
            });

            const store = useFilterStore();
            store.groups = [{ key: 'dimensions' }, { key: 'sdg' }];
            store.level1Filter = 'sdg';

            store.syncToUrl();

            expect(window.history.replaceState).toHaveBeenCalledWith(
                {},
                '',
                expect.stringContaining('filter=sdg'),
            );
        });

        it('removes filter parameter when level1Filter equals default', () => {
            Object.defineProperty(window, 'location', {
                value: { href: 'http://localhost/tiles?filter=dimensions', search: '?filter=dimensions' },
                writable: true,
            });

            const store = useFilterStore();
            store.groups = [{ key: 'dimensions' }];
            store.level1Filter = 'dimensions';

            store.syncToUrl();

            const callArgs = window.history.replaceState.mock.calls[0];
            expect(callArgs[2]).not.toContain('filter=dimensions');
        });

        it('includes search parameter when searchQuery is set', () => {
            Object.defineProperty(window, 'location', {
                value: { href: 'http://localhost/tiles', search: '' },
                writable: true,
            });

            const store = useFilterStore();
            store.searchQuery = 'energy';

            store.syncToUrl();

            expect(window.history.replaceState).toHaveBeenCalledWith(
                {},
                '',
                expect.stringContaining('search=energy'),
            );
        });

        it('includes filterKey parameter when level2Filter has key', () => {
            Object.defineProperty(window, 'location', {
                value: { href: 'http://localhost/tiles', search: '' },
                writable: true,
            });

            const store = useFilterStore();
            store.level2Filter = { title: 'Economy', key: 'economy' };

            store.syncToUrl();

            expect(window.history.replaceState).toHaveBeenCalledWith(
                {},
                '',
                expect.stringContaining('filterKey=economy'),
            );
        });
    });
});
