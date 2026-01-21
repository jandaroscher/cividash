import { defineStore } from 'pinia';

/**
 * Obtain the key of the first group in the provided groups array.
 * @param {Array<Object>} groups - Array of group objects; each group is expected to have a `key` property.
 * @returns {string|null} The `key` of the first group, or `null` if `groups` is not a non-empty array.
 */
function getDefaultGroupKey(groups) {
    return Array.isArray(groups) && groups.length > 0 ? groups[0].key : null;
}

/**
 * Update the browser's URL query parameters to reflect the provided filter state without creating a new history entry.
 * @param {{level1Filter?: string|null, level2Filter?: {key?: string, title?: string|null}, searchQuery?: string, defaultGroupKey?: string|null}} filterState - Filter values to sync to the URL. If running outside a browser the function does nothing. `defaultGroupKey`, when provided, is treated as the implicit level1 filter and will prevent writing the `filter` parameter when `level1Filter` equals `defaultGroupKey`.
 * - `level1Filter`: sets the `filter` query parameter unless it is `null`, missing, or equal to `defaultGroupKey` (in which case `filter` is removed).
 * - `level2Filter.key`: sets the `filterKey` query parameter when present; otherwise `filterKey` is removed.
 * - `searchQuery`: sets the `search` query parameter to its trimmed value when non-empty; otherwise `search` is removed.
 */
function updateUrlWithFilters(filterState) {
    if (typeof window === 'undefined') return;

    const url = new URL(window.location.href);
    const defaultGroupKey = filterState.defaultGroupKey || null;

    // Set or remove filter parameter
    if (filterState.level1Filter && filterState.level1Filter !== defaultGroupKey) {
        url.searchParams.set('filter', filterState.level1Filter);
    } else {
        url.searchParams.delete('filter');
    }

    // Set or remove filterKey parameter
    if (filterState.level2Filter?.key) {
        url.searchParams.set('filterKey', filterState.level2Filter.key);
    } else {
        url.searchParams.delete('filterKey');
    }

    // Set or remove search parameter
    if (filterState.searchQuery && filterState.searchQuery.trim()) {
        url.searchParams.set('search', filterState.searchQuery.trim());
    } else {
        url.searchParams.delete('search');
    }

    // Use replaceState to avoid adding new history entry
    window.history.replaceState({}, '', url.toString());
}

/**
 * Builds a filter state object from the current page URL query parameters.
 *
 * Parses `filter`, `filterKey`, and `search` from the URL and maps them to the store's filter state shape.
 *
 * @returns {{level1Filter: string|null, level2Filter: {key: string|null, title: string|null}, searchQuery: string}|null} The parsed filter state, or `null` when not running in a browser. `level1Filter` is the `filter` query value or `null`; `level2Filter.key` is the `filterKey` query value or `null` and `level2Filter.title` is `null`; `searchQuery` is the `search` query value or an empty string.
 */
function getFilterStateFromUrl() {
    if (typeof window === 'undefined') return null;

    const params = new URLSearchParams(window.location.search);
    const filter = params.get('filter');
    const filterKey = params.get('filterKey');
    const search = params.get('search');

    return {
        level1Filter: filter || null,
        level2Filter: filterKey ? { key: filterKey, title: null } : { title: null, key: null },
        searchQuery: search || '',
    };
}

/**
 * Extracts a display title from an item.
 *
 * If the item provides a string `title`, that string is returned. If `title` is an object,
 * the function returns `title.de` if present, otherwise `title.en`, otherwise an empty string.
 * Returns `null` when `item` is falsy or does not contain a usable `title`.
 *
 * @param {object|null|undefined} item - The item which may contain a `title` property (string or localized object).
 * @returns {string|null} The derived title string, an empty string when a title object exists but has no `de`/`en`, or `null` if no usable title is available.
 */
function getItemTitle(item) {
    if (!item) return null;
    if (typeof item.title === 'string') {
        return item.title;
    }
    if (typeof item.title === 'object' && item.title !== null) {
        return item.title.de || item.title.en || null;
    }
    return null;
}

export const useFilterStore = defineStore('filter', {
    state: () => ({
        level1Filter: null,
        level2Filter: {
            title: null,
            key: null,
        },
        searchQuery: '',
        groups: [],
        loading: {
            groups: false,
        },
        error: null,
    }),
    getters: {
        defaultGroupKey(state) {
            return getDefaultGroupKey(state.groups);
        },
    },
    actions: {
        setLevel1Filter(filter) {
            this.level1Filter = filter;
            this.level2Filter = { title: null, key: null };
            this.syncToUrl();
        },
        setLevel2Filter(filter) {
            this.level2Filter = filter;
            this.syncToUrl();
        },
        clearLevel2Filter() {
            this.level2Filter = { title: null, key: null };
            this.syncToUrl();
        },
        setSearchQuery(query) {
            this.searchQuery = query || '';
            this.syncToUrl();
        },
        clearFilters() {
            this.level1Filter = this.defaultGroupKey;
            this.level2Filter = { title: null, key: null };
            this.searchQuery = '';
            this.syncToUrl();
        },
        setGroups(groups) {
            this.groups = Array.isArray(groups) ? groups : [];

            if (!this.level1Filter || !this.groups.some(group => group.key === this.level1Filter)) {
                this.level1Filter = this.defaultGroupKey;
                this.level2Filter = { title: null, key: null };
            }
        },
        setLoading(loading) {
            this.loading.groups = loading;
        },
        setError(error) {
            this.error = error;
        },
        syncToUrl() {
            updateUrlWithFilters({
                level1Filter: this.level1Filter,
                level2Filter: this.level2Filter,
                searchQuery: this.searchQuery,
                defaultGroupKey: this.defaultGroupKey,
            });
        },
        restoreFromUrl() {
            if (typeof window === 'undefined') return;

            const params = new URLSearchParams(window.location.search);
            const hasFilterParam = params.has('filter');
            const hasFilterKeyParam = params.has('filterKey');
            const hasSearchParam = params.has('search');

            if (!hasFilterParam && !hasFilterKeyParam && !hasSearchParam) {
                this.level1Filter = this.defaultGroupKey;
                this.level2Filter = { title: null, key: null };
                this.searchQuery = '';
                return;
            }

            const urlState = getFilterStateFromUrl();
            if (!urlState) {
                this.level1Filter = this.defaultGroupKey;
                this.level2Filter = { title: null, key: null };
                this.searchQuery = '';
                return;
            }

            if (hasFilterParam && urlState.level1Filter) {
                this.level1Filter = urlState.level1Filter;
            } else {
                this.level1Filter = this.defaultGroupKey;
            }

            if (this.level1Filter && !this.groups.some(group => group.key === this.level1Filter)) {
                this.level1Filter = this.defaultGroupKey;
                this.level2Filter = { title: null, key: null };
            }

            if (hasFilterKeyParam && urlState.level2Filter?.key) {
                const group = this.groups.find(g => g.key === this.level1Filter);
                const item = group?.items?.find(entry =>
                    entry.id?.toString() === urlState.level2Filter.key ||
                    entry.key === urlState.level2Filter.key
                );

                this.level2Filter = {
                    key: urlState.level2Filter.key,
                    title: getItemTitle(item),
                };
            } else {
                this.level2Filter = { title: null, key: null };
            }

            this.searchQuery = hasSearchParam ? (urlState.searchQuery || '') : '';
        },
    },
});