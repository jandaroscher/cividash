import { defineStore } from 'pinia';

/**
 * Reflect the given filter state in the browser URL query parameters without creating a history entry.
 * @param {{level1Filter?: string, level2Filter?: {key?: string, title?: string|null}, searchQuery?: string}} filterState - The filter state to encode into the URL. 
 *   - level1Filter: one of 'dimensions' | 'fields' | 'sdg' (when 'dimensions' or absent, the `filter` param is removed).
 *   - level2Filter.key: identifier to store as `filterKey` query parameter (absent removes the param).
 *   - searchQuery: free-text query to store as `search` query parameter (trimmed; empty or whitespace removes the param).
 */
function updateUrlWithFilters(filterState) {
    if (typeof window === 'undefined') return;
    
    const url = new URL(window.location.href);
    
    // Set or remove filter parameter
    if (filterState.level1Filter && filterState.level1Filter !== 'dimensions') {
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
 * Constructs a filter state object from the page URL's query parameters.
 *
 * Reads `filter`, `filterKey`, and `search` from the query string and maps them to
 * the returned state's properties. Defaults: `level1Filter` is `"dimensions"`,
 * `level2Filter.key` is `null` when `filterKey` is absent, `level2Filter.title` is `null`,
 * and `searchQuery` is an empty string.
 *
 * @returns {{level1Filter: string, level2Filter: {key: string|null, title: string|null}, searchQuery: string}|null}
 *   The constructed filter state, or `null` when not running in a browser environment.
 */
function getFilterStateFromUrl() {
    if (typeof window === 'undefined') return null;
    
    const params = new URLSearchParams(window.location.search);
    const filter = params.get('filter');
    const filterKey = params.get('filterKey');
    const search = params.get('search');
    
    return {
        level1Filter: filter || 'dimensions',
        level2Filter: filterKey ? { key: filterKey, title: null } : { title: null, key: null },
        searchQuery: search || '',
    };
}

export const useFilterStore = defineStore('filter', {
    state: () => ({
        level1Filter: 'dimensions', // 'dimensions', 'fields', 'sdg' - default to 'dimensions' like reference app
        level2Filter: {
            title: null,
            key: null,
        },
        searchQuery: '', // Free-text search query
        // Cache for filter data to avoid repeated API calls
        dimensions: [],
        fields: [],
        sdgZiele: [],
        loading: {
            dimensions: false,
            fields: false,
            sdg: false,
        },
    }),
    actions: {
        setLevel1Filter(filter) {
            this.level1Filter = filter;
            // Reset level 2 filter when level 1 changes
            this.level2Filter = { title: null, key: null };
            // Sync to URL
            this.syncToUrl();
        },
        setLevel2Filter(filter) {
            this.level2Filter = filter;
            // Sync to URL
            this.syncToUrl();
        },
        setSearchQuery(query) {
            this.searchQuery = query || '';
            // Sync to URL
            this.syncToUrl();
        },
        clearFilters() {
            // Reset to default filter state
            this.level1Filter = 'dimensions';
            this.level2Filter = { title: null, key: null };
            this.searchQuery = '';
            // Sync to URL
            this.syncToUrl();
        },
        setDimensions(dimensions) {
            this.dimensions = dimensions;
        },
        setFields(fields) {
            this.fields = fields;
        },
        setSDGZiele(sdgZiele) {
            this.sdgZiele = sdgZiele;
        },
        setLoading(filterType, loading) {
            if (Object.prototype.hasOwnProperty.call(this.loading, filterType)) {
                this.loading[filterType] = loading;
            }
        },
        /**
         * Sync current filter state to URL
         */
        syncToUrl() {
            updateUrlWithFilters({
                level1Filter: this.level1Filter,
                level2Filter: this.level2Filter,
                searchQuery: this.searchQuery,
            });
        },
        /**
         * Restore filter state from URL (used for deep-linking)
         */
        restoreFromUrl() {
            if (typeof window === 'undefined') return;
            
            const params = new URLSearchParams(window.location.search);
            const hasFilterParam = params.has('filter');
            const hasFilterKeyParam = params.has('filterKey');
            const hasSearchParam = params.has('search');
            
            // If no filter-related parameters exist, reset to default state
            if (!hasFilterParam && !hasFilterKeyParam && !hasSearchParam) {
                this.level1Filter = 'dimensions';
                this.level2Filter = { title: null, key: null };
                this.searchQuery = '';
                return;
            }
            
            const urlState = getFilterStateFromUrl();
            if (!urlState) {
                // Fallback: reset to default state
                this.level1Filter = 'dimensions';
                this.level2Filter = { title: null, key: null };
                this.searchQuery = '';
                return;
            }
            
            // Restore level1Filter
            if (hasFilterParam && urlState.level1Filter && ['dimensions', 'fields', 'sdg'].includes(urlState.level1Filter)) {
                this.level1Filter = urlState.level1Filter;
            } else {
                // No filter parameter in URL - reset to default
                this.level1Filter = 'dimensions';
            }
            
            // Restore level2Filter and try to find title from loaded data
            if (hasFilterKeyParam && urlState.level2Filter?.key) {
                let title = null;
                
                // Try to find title from already loaded filter data
                if (this.level1Filter === 'dimensions' && this.dimensions.length > 0) {
                    const dimension = this.dimensions.find(d => 
                        d.key === urlState.level2Filter.key || 
                        d.id?.toString() === urlState.level2Filter.key
                    );
                    if (dimension) {
                        title = typeof dimension.title === 'string' 
                            ? dimension.title 
                            : (dimension.title?.de || dimension.title?.en || '');
                    }
                } else if (this.level1Filter === 'fields' && this.fields.length > 0) {
                    const field = this.fields.find(f => 
                        f.id?.toString() === urlState.level2Filter.key
                    );
                    if (field) {
                        title = typeof field.slug === 'string' 
                            ? field.slug 
                            : (field.slug?.de || field.slug?.en || '');
                    }
                } else if (this.level1Filter === 'sdg' && this.sdgZiele.length > 0) {
                    const sdg = this.sdgZiele.find(s => 
                        s.id?.toString() === urlState.level2Filter.key
                    );
                    if (sdg) {
                        title = typeof sdg.title === 'string' 
                            ? sdg.title 
                            : (sdg.title?.de || sdg.title?.en || `SDG ${sdg.number || ''}`);
                    }
                }
                
                this.level2Filter = {
                    key: urlState.level2Filter.key,
                    title: title || null,
                };
            } else {
                // No filterKey parameter in URL - reset to default
                this.level2Filter = { title: null, key: null };
            }
            
            // Restore searchQuery
            this.searchQuery = hasSearchParam ? (urlState.searchQuery || '') : '';
        },
    },
});
