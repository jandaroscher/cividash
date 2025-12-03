import { defineStore } from 'pinia';

export const useFilterStore = defineStore('filter', {
    state: () => ({
        level1Filter: 'dimensions', // 'dimensions', 'fields', 'sdg' - default to 'dimensions' like reference app
        level2Filter: {
            title: null,
            key: null,
        },
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
        },
        setLevel2Filter(filter) {
            this.level2Filter = filter;
        },
        clearFilters() {
            // Reset to default filter state
            this.level1Filter = 'dimensions';
            this.level2Filter = { title: null, key: null };
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
            if (this.loading.hasOwnProperty(filterType)) {
                this.loading[filterType] = loading;
            }
        },
    },
});

