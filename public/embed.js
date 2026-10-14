/*
 * CiviDash tile embed loader.
 *
 *   <cividash-tile slug="my-tile" lang="de" label="My tile"></cividash-tile>
 *   <script src="https://dashboard.example.org/embed.js" defer></script>
 *
 * Renders the tile in an iframe (the host page never runs dashboard code) and
 * resizes it to the height reported by /embed/tiles/{slug}.
 */
(() => {
    // currentScript is null in module scripts; the snippet uses a classic script tag.
    if (customElements.get('cividash-tile') || !document.currentScript) return;

    // The script is served by the dashboard itself, so its origin is the tenant.
    const origin = new URL(document.currentScript.src).origin;

    class CividashTile extends HTMLElement {
        connectedCallback() {
            const slug = this.getAttribute('slug');
            if (this.iframe || !slug) return;

            const iframe = document.createElement('iframe');
            const prefix = this.getAttribute('lang') === 'en' ? '/en' : '';
            iframe.src = `${origin}${prefix}/embed/tiles/${encodeURIComponent(slug)}`;
            iframe.title = this.getAttribute('label') || slug;
            iframe.loading = 'lazy';
            iframe.style.cssText = 'display:block;width:100%;height:400px;border:0';

            this.style.display ||= 'block';
            this.iframe = iframe;
            this.appendChild(iframe);
        }
    }

    window.addEventListener('message', (event) => {
        if (event.origin !== origin || event.data?.type !== 'cividash:resize') return;
        const height = Number(event.data.height);
        if (!Number.isFinite(height) || height <= 0) return;

        for (const tile of document.querySelectorAll('cividash-tile')) {
            if (tile.iframe?.contentWindow === event.source) {
                tile.iframe.style.height = `${height}px`;
            }
        }
    });

    customElements.define('cividash-tile', CividashTile);
})();
