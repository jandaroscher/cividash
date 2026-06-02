import { ref } from 'vue';
import { getApiBaseUrl } from '../utils/api';

/**
 * Composable for triggering data exports.
 *
 * Builds query strings against the public /api/exports/* endpoints,
 * downloads the streamed file via fetch → Blob → <a download>, and
 * exposes loading/error state so components can show a spinner or toast.
 */
export function useExport() {
    const loading = ref(false);
    const error = ref(null);

    /**
     * Download the given URL as a file. Uses fetch so we can await the
     * response, surface errors, and reuse cookies/auth like the rest
     * of the app instead of relying on a bare <a> click.
     *
     * @param {string} url
     * @param {string} filename Fallback name if the server does not send
     *                          a Content-Disposition header.
     */
    async function download(url, filename) {
        loading.value = true;
        error.value = null;

        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json, text/csv, */*' },
            });

            if (!response.ok) {
                throw new Error(`Export failed (HTTP ${response.status})`);
            }

            const disposition = response.headers.get('Content-Disposition') || '';
            const match = disposition.match(/filename\*=UTF-8''([^;]+)|filename="?([^";]+)"?/i);
            const rawServerName = match ? (match[1] || match[2] || '') : '';
            // decodeURIComponent throws on malformed input; fall back to the
            // raw token instead of aborting the whole download path.
            let serverName = '';
            if (rawServerName) {
                try {
                    serverName = decodeURIComponent(rawServerName);
                } catch {
                    serverName = rawServerName;
                }
            }
            const finalName = serverName || filename;

            const blob = await response.blob();
            const objectUrl = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = objectUrl;
            anchor.download = finalName;
            document.body.appendChild(anchor);
            anchor.click();
            document.body.removeChild(anchor);
            URL.revokeObjectURL(objectUrl);
        } catch (e) {
            error.value = e instanceof Error ? e : new Error(String(e));
            throw error.value;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Build a query string from a set of export parameters.
     *
     * @param {Object} params
     * @param {'json'|'csv'} [params.format]
     * @param {'de'|'en'} [params.locale]
     * @param {string[]} [params.fields]
     * @param {number[]} [params.tiles]
     * @param {string[]} [params.categories]
     * @param {number} [params.yearFrom]
     * @param {number} [params.yearTo]
     * @returns {string}
     */
    function buildQuery({ format, locale, fields, tiles, categories, yearFrom, yearTo } = {}) {
        const search = new URLSearchParams();
        if (format) search.set('format', format);
        if (locale) search.set('locale', locale);
        if (Array.isArray(fields)) {
            for (const field of fields) search.append('fields[]', field);
        }
        if (Array.isArray(tiles)) {
            for (const id of tiles) search.append('tiles[]', String(id));
        }
        if (Array.isArray(categories)) {
            for (const key of categories) search.append('categories[]', key);
        }
        if (yearFrom !== undefined && yearFrom !== null) search.set('year_from', String(yearFrom));
        if (yearTo !== undefined && yearTo !== null) search.set('year_to', String(yearTo));
        const qs = search.toString();
        return qs === '' ? '' : `?${qs}`;
    }

    function tileUrl(slug, params) {
        return `${getApiBaseUrl()}/api/tiles/${encodeURIComponent(slug)}/export${buildQuery(params)}`;
    }

    function filteredTilesUrl(params) {
        return `${getApiBaseUrl()}/api/exports/tiles${buildQuery(params)}`;
    }

    function catalogUrl(params) {
        return `${getApiBaseUrl()}/api/exports/catalog${buildQuery(params)}`;
    }

    return {
        loading,
        error,
        download,
        tileUrl,
        filteredTilesUrl,
        catalogUrl,
    };
}
