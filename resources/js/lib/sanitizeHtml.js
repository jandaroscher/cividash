// Deliberately restrictive PoC sanitizer for Approach B (Sandboxed Slot,
//). Backend-authored HTML slotted into a TileCard region (badge,
// trend text) has no legitimate need for scripts, event handlers, styles, or
// links - so the whitelist only keeps inline text formatting.
//
// Whitelist: b, strong, em, span, br. No attributes except `class`.
// Everything else is unwrapped (tag dropped, text/children kept) except
// script/style, whose entire subtree is dropped (unwrapping those would leak
// their text content as plain text).
//
// Limits (this is a PoC, not a hardened sanitizer):
// - Browser-only (uses DOMParser); fine here since the slot content is only
//   ever rendered client-side.
// - Whitelist pass over the parsed DOM, not a full sanitizer library - no
//   CSS/URL scheme handling, no MathML/SVG namespace awareness, etc.
// - For real CMS rich-text content (links, images, broader tag set), use the
//   existing DOMPurify-based `resources/js/utils/sanitizeHtml.js` instead.
// - upgrade path: swap this out for DOMPurify with a custom config if the
//   whitelist ever needs to grow beyond plain inline text formatting.

const ALLOWED_TAGS = new Set(['B', 'STRONG', 'EM', 'SPAN', 'BR']);
const ALLOWED_ATTRS = new Set(['class']);
const DROP_SUBTREE_TAGS = new Set(['SCRIPT', 'STYLE']);

function sanitizeInto(node, out) {
    if (node.nodeType === Node.TEXT_NODE) {
        out.appendChild(node.cloneNode());
        return;
    }
    if (node.nodeType !== Node.ELEMENT_NODE) {
        return; // drop comments and everything else
    }
    if (DROP_SUBTREE_TAGS.has(node.tagName)) {
        return;
    }

    const isAllowed = ALLOWED_TAGS.has(node.tagName);
    const target = isAllowed ? document.createElement(node.tagName.toLowerCase()) : out;

    if (isAllowed) {
        for (const attr of node.attributes) {
            if (ALLOWED_ATTRS.has(attr.name.toLowerCase())) {
                target.setAttribute(attr.name, attr.value);
            }
        }
    }

    for (const child of node.childNodes) {
        sanitizeInto(child, target);
    }

    if (isAllowed) {
        out.appendChild(target);
    }
}

/**
 * Sanitize an HTML string down to a tight whitelist of inline text tags.
 * @param {string} html
 * @returns {string}
 */
export function sanitize(html) {
    if (typeof html !== 'string' || html === '') return '';
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const out = document.createElement('div');
    for (const child of doc.body.childNodes) {
        sanitizeInto(child, out);
    }
    return out.innerHTML;
}
