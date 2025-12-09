/**
 * Composable for managing page meta tags (title, description, OG tags)
 * Updates document head dynamically for SEO
 */

// Track tags created by this composable
const managedTags = new Set();

/**
 * Set the document title when running in a browser.
 * @param {string} title - The title to assign to document.title.
 */
export function setTitle(title) {
    if (title && typeof document !== 'undefined') {
        document.title = title;
    }
}

/**
 * Sets or updates a meta tag in the document head.
 * @param {string} name - The meta attribute key (e.g., "description" or "og:title").
 * @param {string} content - The content value to assign to the meta tag.
 * @param {string} [type='name'] - Which attribute to use: `'name'` or `'property'`.
 */
export function setMetaTag(name, content, type = 'name') {
    if (content == null || typeof document === 'undefined') {
        return;
    }
    
    const attribute = type === 'property' ? 'property' : 'name';
    let tag = document.querySelector(`meta[${attribute}="${name}"]`);
    
    if (!tag) {
        tag = document.createElement('meta');
        tag.setAttribute(attribute, name);
        document.head.appendChild(tag);
    }
    
    tag.setAttribute('content', content);
    managedTags.add(`${attribute}:${name}`);
}

/**
 * Remove meta tags that are no longer needed
 */
export function clearManagedTags() {
    if (typeof document === 'undefined') return;
    
    managedTags.forEach((tagId) => {
        const [attribute, name] = tagId.split(':');
        const tag = document.querySelector(`meta[${attribute}="${name}"]`);
        if (tag) {
            tag.remove();
        }
    });
    managedTags.clear();
}

/**
 * Set the meta description
 * @param {string} description - Page description
 */
export function setDescription(description) {
    setMetaTag('description', description, 'name');
}

/**
 * Apply multiple Open Graph meta tags to the document head.
 *
 * Iterates over the provided object and creates or updates meta tags for each entry;
 * keys missing the `og:` prefix will be prefixed automatically, and entries with falsy
 * values are ignored. No action is performed when running outside a browser environment.
 *
 * @param {Object<string, string>} ogTags - Mapping of Open Graph keys to values (e.g. `{ title: "...", "og:image": "..." }`).
 */
export function setOGTags(ogTags) {
    if (!ogTags || typeof document === 'undefined') {
        return;
    }
    
    Object.entries(ogTags).forEach(([key, value]) => {
        if (value) {
            const property = key.startsWith('og:') ? key : `og:${key}`;
            setMetaTag(property, value, 'property');
        }
    });
}

/**
 * Set Twitter Card meta tags in the document head.
 *
 * Iterates the provided key/value map and creates or updates meta tags for each truthy value.
 * Keys may be provided with or without the "twitter:" prefix; the prefix will be added if missing.
 * This function does nothing when run outside a browser (no global `document`) or when `twitterTags` is falsy.
 * @param {Object<string, string>} twitterTags - Map of Twitter tag names to values (e.g. `{ card: 'summary', 'twitter:title': 'Title' }`).
 */
export function setTwitterTags(twitterTags) {
    if (!twitterTags || typeof document === 'undefined') {
        return;
    }
    
    Object.entries(twitterTags).forEach(([key, value]) => {
        if (value) {
            const name = key.startsWith('twitter:') ? key : `twitter:${key}`;
            setMetaTag(name, value, 'name');
        }
    });
}

/**
 * Apply a set of page metadata (title, description, Open Graph, and Twitter Card tags) to the document head.
 *
 * If `meta` is falsy or the environment has no DOM (no `document`), the function does nothing.
 *
 * @param {Object} meta - Metadata for the page.
 * @param {string} [meta.title] - Page title.
 * @param {string} [meta.description] - Page description.
 * @param {string} [meta.image] - Default image URL used for Open Graph and Twitter tags.
 * @param {string} [meta.url] - Canonical URL for the page.
 * @param {Object} [meta.og] - Additional Open Graph properties (keys may be full `og:` names or plain names).
 * @param {Object} [meta.twitter] - Twitter Card properties (keys may be full `twitter:` names or plain names).
 * @note meta.og properties will override the computed defaults (og:title, og:description, og:image) set above.
 *       Use either shorthand keys (e.g., title) or full keys (e.g., 'og:title') in meta.og, but not both to avoid ambiguity.
 */
export function setMetaTags(meta) {
    if (!meta || typeof document === 'undefined') {
        return;
    }
    
    // Clear previous tags before setting new ones
    clearManagedTags();
    
    // Set title
    if (meta.title) {
        setTitle(meta.title);
    }
    
    // Set description
    if (meta.description) {
        setDescription(meta.description);
    }
    
    // Set OG tags
    // Note: meta.og properties will override defaults set above
    const ogTags = {
        'og:title': meta.title || meta.og?.title,
        'og:description': meta.description || meta.og?.description,
        'og:image': meta.image || meta.og?.image,
        'og:type': meta.og?.type || 'website',
        'og:url': meta.url || meta.og?.url || (typeof window !== 'undefined' ? window.location.href : ''),
        ...meta.og,
    };
    setOGTags(ogTags);
    
    // Set Twitter Card tags
    if (meta.twitter) {
        setTwitterTags(meta.twitter);
    } else {
        // Default Twitter Card
        setTwitterTags({
            'twitter:card': 'summary_large_image',
            'twitter:title': meta.title,
            'twitter:description': meta.description,
            'twitter:image': meta.image,
        });
    }
}

/**
 * Provide functions to set the document title and common meta tags for use in Vue components.
 * @returns {Object} An object exposing the following functions: `setTitle`, `setDescription`, `setMetaTag`, `setOGTags`, `setTwitterTags`, and `setMetaTags`.
 */
export function useMeta() {
    return {
        setTitle,
        setDescription,
        setMetaTag,
        setOGTags,
        setTwitterTags,
        setMetaTags,
    };
}
