import { describe, it, expect } from 'vitest';
import { buildEmbedSnippets } from '@/utils/embedSnippets';

describe('buildEmbedSnippets', () => {
    it('builds the loader and iframe codes for German tiles', () => {
        const code = buildEmbedSnippets({
            origin: 'https://dash.example',
            slug: 'co2-emissionen',
            locale: 'de',
            title: 'CO2-Emissionen',
        });

        expect(code.loader).toBe(
            '<cividash-tile slug="co2-emissionen" lang="de" label="CO2-Emissionen"></cividash-tile>\n'
            + '<script src="https://dash.example/embed.js" defer></script>',
        );
        expect(code.iframe).toContain('src="https://dash.example/embed/tiles/co2-emissionen"');
        expect(code.iframe).toContain('title="CO2-Emissionen"');
    });

    it('uses the /en prefix for English tiles', () => {
        const code = buildEmbedSnippets({ origin: 'https://dash.example', slug: 'co2', locale: 'en' });

        expect(code.loader).toContain('lang="en"');
        expect(code.iframe).toContain('src="https://dash.example/en/embed/tiles/co2"');
    });

    it('escapes titles so they cannot break out of the attribute', () => {
        const code = buildEmbedSnippets({
            origin: 'https://dash.example',
            slug: 'x',
            locale: 'de',
            title: 'A "B" <script>&',
        });

        expect(code.loader).toContain('label="A &quot;B&quot; &lt;script&gt;&amp;"');
        expect(code.iframe).not.toContain('<script>');
    });
});
