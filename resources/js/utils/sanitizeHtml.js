import DOMPurify from 'dompurify';

/**
 * Check whether an href points to an external URL.
 *
 * Internal links include relative paths, hash anchors, mailto:, tel:,
 * and any absolute URL whose hostname matches `window.location.hostname`.
 *
 * @param {string|null|undefined} href
 * @returns {boolean}
 */
export function isExternalUrl(href) {
    if (!href) return false;

    // Hash anchors, relative paths, mailto, tel — always internal
    if (href.startsWith('#') || href.startsWith('/') || href.startsWith('mailto:') || href.startsWith('tel:')) {
        return false;
    }

    try {
        const url = new URL(href, window.location.origin);

        // Only treat http(s) as potentially external
        if (url.protocol !== 'http:' && url.protocol !== 'https:') {
            return false;
        }

        return url.hostname !== window.location.hostname;
    } catch {
        return false;
    }
}

// Register the DOMPurify hook once (ES module singleton)
let hookRegistered = false;

function ensureHookRegistered() {
    if (hookRegistered) return;
    hookRegistered = true;

    DOMPurify.addHook('afterSanitizeAttributes', (node) => {
        if (node.tagName !== 'A') return;

        const href = node.getAttribute('href');
        if (isExternalUrl(href)) {
            node.setAttribute('target', '_blank');
            node.setAttribute('rel', 'noopener noreferrer');
        }
    });
}

/**
 * Sanitize HTML with DOMPurify, automatically adding `target="_blank"` and
 * `rel="noopener noreferrer"` to external links.
 *
 * @param {string} html
 * @returns {string}
 */
export function sanitizeHtml(html) {
    ensureHookRegistered();

        ADD_ATTR: ['target', 'rel'],
    });
}
