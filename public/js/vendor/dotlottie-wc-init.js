// Registers <dotlottie-wc> for the Filament admin panel and points its WASM
// renderer at our self-hosted copy instead of the jsdelivr/unpkg CDN default
// (no third-party hosts at runtime, see tests/Feature/Privacy/NoThirdPartyHostsTest.php).
//
// The element must NOT exist in the DOM before setWasmUrl() has run: importing
// dotlottie-wc.js registers the custom element as a side effect, and the
// browser upgrades any already-connected <dotlottie-wc> tag immediately,
// which starts loading the default (CDN) WASM before the next line here gets
// a chance to override it — verified empirically, see PR description. So the
// Blade views render a `[data-dotlottie-wc]` placeholder <div> instead, and
// this script swaps in the real element only afterwards.
import { setWasmUrl } from './dotlottie-wc.js';

setWasmUrl(new URL('./dotlottie-player.wasm', import.meta.url).href);

function upgrade(root) {
    root.querySelectorAll('[data-dotlottie-wc]').forEach((placeholder) => {
        const el = document.createElement('dotlottie-wc');
        for (const { name, value } of [...placeholder.attributes]) {
            if (name !== 'data-dotlottie-wc') el.setAttribute(name, value);
        }
        placeholder.replaceWith(el);
    });
}

upgrade(document);

// Filament/Livewire re-renders tables and form fields without a full page
// reload (pagination, sorting, record selection), so keep upgrading newly
// added placeholders. Re-scanning the whole document is cheap and idempotent
// (already-upgraded elements no longer match the selector).
new MutationObserver((mutations) => {
    if (mutations.some((m) => m.addedNodes.length > 0)) upgrade(document);
}).observe(document.body, { childList: true, subtree: true });
