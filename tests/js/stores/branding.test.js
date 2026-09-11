import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useBrandingStore } from '@/stores/branding';
import { mockFetch, mockFetchError } from '../setup';

describe('brandingStore', () => {
    let setPropertySpy;

    beforeEach(() => {
        setActivePinia(createPinia());
        vi.restoreAllMocks();
        setPropertySpy = vi.spyOn(document.documentElement.style, 'setProperty');
    });

    describe('initial state', () => {
        it('has correct default values', () => {
            const store = useBrandingStore();
            expect(store.primaryColor).toBe('#1976d2');
            expect(store.secondaryColor).toBe('#0d47a1');
            expect(store.logoUrl).toBeNull();
            expect(store.accentColor).toBeNull();
            expect(store.typographyFontFamily).toBe('Open Sans');
            expect(store.typographyFontWeights).toEqual([400, 600, 700]);
            expect(store.typographyFontSizes).toEqual({
                base: '1rem',
                small: '0.875rem',
                large: '1.125rem',
                h1: '3rem',
                h2: '2.25rem',
                h3: '1.875rem',
                h4: '1.5rem',
                h5: '1.25rem',
                h6: '1.125rem',
            });
            expect(store.customFontName).toBeNull();
            expect(store.customFontFile).toBeNull();
            expect(store.sliderColors).toEqual({
                rail: '#191919',
                handle: null,
                handleBorder: '#191919',
            });
            expect(store.backgroundColor).toBeNull();
            expect(store.cardBackgroundColor).toBe('#FFFFFF');
            expect(store.heroBackgroundColor).toBe('#111827');
            expect(store.overlayBackgroundColor).toBe('rgba(0,0,0,0.4)');
            expect(store.headerBackgroundColor).toBe('#FFFFFF');
            expect(store.footerBackgroundColor).toBe('#E5E7EB');
            expect(store.textPrimaryColor).toBeNull();
            expect(store.textSecondaryColor).toBeNull();
            expect(store.textInverseColor).toBeNull();
            expect(store.linkColor).toBeNull();
            expect(store.linkHoverColor).toBeNull();
            expect(store.borderColor).toBeNull();
            expect(store.dividerColor).toBeNull();
            expect(store.shadowColor).toBe('#000000');
            expect(store.navTextColor).toBe('#374151');
            expect(store.navTextColorInactive).toBe('#9CA3AF');
            expect(store.navHoverColor).toBe('#e30613');
        });
    });

    describe('fetch', () => {
        const fullBrandingResponse = {
            data: {
                primary_color: '#FF0000',
                secondary_color: '#00FF00',
                logo_url: '/images/logo.png',
                accent_color: '#0000FF',
                typography_font_family: 'Roboto',
                typography_font_weights: [300, 400, 700],
                typography_font_sizes: { base: '1.125rem', h1: '3.5rem' },
                typography_custom_font_name: null,
                typography_custom_font_file: null,
                slider_colors: { rail: '#333333', handle: '#FF0000', handleBorder: '#000000' },
                background_color: '#F5F5F5',
                card_background_color: '#EEEEEE',
                hero_background_color: '#222222',
                overlay_background_color: 'rgba(0,0,0,0.6)',
                header_background_color: '#FAFAFA',
                footer_background_color: '#CCCCCC',
                text_primary_color: '#111111',
                text_secondary_color: '#666666',
                text_inverse_color: '#FFFFFF',
                link_color: '#0066CC',
                link_hover_color: '#004499',
                border_color: '#DDDDDD',
                divider_color: '#EEEEEE',
                shadow_color: '#333333',
                nav_text_color: '#444444',
                nav_text_color_inactive: '#AAAAAA',
                nav_hover_color: '#FF6666',
            },
        };

        it('updates state from API response', async () => {
            globalThis.fetch = mockFetch(fullBrandingResponse);

            const store = useBrandingStore();
            await store.fetch();

            expect(store.primaryColor).toBe('#FF0000');
            expect(store.secondaryColor).toBe('#00FF00');
            expect(store.logoUrl).toBe('/images/logo.png');
            expect(store.accentColor).toBe('#0000FF');
            expect(store.typographyFontFamily).toBe('Roboto');
            expect(store.typographyFontWeights).toEqual([300, 400, 700]);
            expect(store.typographyFontSizes.base).toBe('1.125rem');
            expect(store.typographyFontSizes.h1).toBe('3.5rem');
            // Merged with defaults, so original keys still exist
            expect(store.typographyFontSizes.small).toBe('0.875rem');
            expect(store.sliderColors).toEqual({ rail: '#333333', handle: '#FF0000', handleBorder: '#000000' });
            expect(store.backgroundColor).toBe('#F5F5F5');
            expect(store.cardBackgroundColor).toBe('#EEEEEE');
            expect(store.heroBackgroundColor).toBe('#222222');
            expect(store.overlayBackgroundColor).toBe('rgba(0,0,0,0.6)');
            expect(store.headerBackgroundColor).toBe('#FAFAFA');
            expect(store.footerBackgroundColor).toBe('#CCCCCC');
            expect(store.textPrimaryColor).toBe('#111111');
            expect(store.textSecondaryColor).toBe('#666666');
            expect(store.textInverseColor).toBe('#FFFFFF');
            expect(store.linkColor).toBe('#0066CC');
            expect(store.linkHoverColor).toBe('#004499');
            expect(store.borderColor).toBe('#DDDDDD');
            expect(store.dividerColor).toBe('#EEEEEE');
            expect(store.shadowColor).toBe('#333333');
            expect(store.navTextColor).toBe('#444444');
            expect(store.navTextColorInactive).toBe('#AAAAAA');
            expect(store.navHoverColor).toBe('#FF6666');
        });

        it('calls API with correct URL and credentials', async () => {
            globalThis.fetch = mockFetch(fullBrandingResponse);

            const store = useBrandingStore();
            await store.fetch();

            expect(globalThis.fetch).toHaveBeenCalledWith(
                expect.stringContaining('/api/config/branding'),
                expect.objectContaining({ credentials: 'include' }),
            );
        });

        it('applies CSS variables to document.documentElement', async () => {
            globalThis.fetch = mockFetch(fullBrandingResponse);

            const store = useBrandingStore();
            await store.fetch();

            expect(setPropertySpy).toHaveBeenCalledWith('--primary-color', '#FF0000');
            expect(setPropertySpy).toHaveBeenCalledWith('--secondary-color', '#00FF00');
            expect(setPropertySpy).toHaveBeenCalledWith('--accent-color', '#0000FF');
            expect(setPropertySpy).toHaveBeenCalledWith('--slider-rail-color', '#333333');
            expect(setPropertySpy).toHaveBeenCalledWith('--slider-handle-color', '#FF0000');
            expect(setPropertySpy).toHaveBeenCalledWith('--card-background-color', '#EEEEEE');
            expect(setPropertySpy).toHaveBeenCalledWith('--hero-background-color', '#222222');
            expect(setPropertySpy).toHaveBeenCalledWith('--shadow-color', '#333333');
            expect(setPropertySpy).toHaveBeenCalledWith('--nav-text-color', '#444444');
            expect(setPropertySpy).toHaveBeenCalledWith('--nav-text-color-inactive', '#AAAAAA');
            expect(setPropertySpy).toHaveBeenCalledWith('--nav-hover-color', '#FF6666');
        });

        it('applies font family CSS variables', async () => {
            globalThis.fetch = mockFetch(fullBrandingResponse);

            const store = useBrandingStore();
            await store.fetch();

            const expectedFontFamily = "'Roboto', ui-sans-serif, system-ui, sans-serif";
            expect(setPropertySpy).toHaveBeenCalledWith('--font-family', expectedFontFamily);
            expect(setPropertySpy).toHaveBeenCalledWith('--font-sans', expectedFontFamily);
        });

        it('applies font size CSS variables', async () => {
            globalThis.fetch = mockFetch(fullBrandingResponse);

            const store = useBrandingStore();
            await store.fetch();

            expect(setPropertySpy).toHaveBeenCalledWith('--font-size-base', '1.125rem');
            expect(setPropertySpy).toHaveBeenCalledWith('--font-size-h1', '3.5rem');
        });

        it('applies text color CSS variables when provided', async () => {
            globalThis.fetch = mockFetch(fullBrandingResponse);

            const store = useBrandingStore();
            await store.fetch();

            expect(setPropertySpy).toHaveBeenCalledWith('--text-primary-color', '#111111');
            expect(setPropertySpy).toHaveBeenCalledWith('--text-secondary-color', '#666666');
            expect(setPropertySpy).toHaveBeenCalledWith('--text-inverse-color', '#FFFFFF');
            expect(setPropertySpy).toHaveBeenCalledWith('--link-color', '#0066CC');
            expect(setPropertySpy).toHaveBeenCalledWith('--link-hover-color', '#004499');
        });

        it('applies background color CSS variable when set', async () => {
            globalThis.fetch = mockFetch(fullBrandingResponse);

            const store = useBrandingStore();
            await store.fetch();

            expect(setPropertySpy).toHaveBeenCalledWith('--background-color', '#F5F5F5');
        });

        it('applies border and divider CSS variables when set', async () => {
            globalThis.fetch = mockFetch(fullBrandingResponse);

            const store = useBrandingStore();
            await store.fetch();

            expect(setPropertySpy).toHaveBeenCalledWith('--border-color', '#DDDDDD');
            expect(setPropertySpy).toHaveBeenCalledWith('--divider-color', '#EEEEEE');
        });

        it('handles null logo gracefully', async () => {
            const responseWithNullLogo = {
                data: {
                    ...fullBrandingResponse.data,
                    logo_url: null,
                },
            };
            globalThis.fetch = mockFetch(responseWithNullLogo);

            const store = useBrandingStore();
            await store.fetch();

            expect(store.logoUrl).toBeNull();
        });

        it('preserves default values when API returns null/undefined for optional fields', async () => {
            const minimalResponse = {
                data: {
                    primary_color: null,
                    secondary_color: null,
                    logo_url: null,
                    accent_color: null,
                    typography_font_family: null,
                    typography_font_weights: null,
                    typography_font_sizes: null,
                    typography_custom_font_name: undefined,
                    typography_custom_font_file: undefined,
                    slider_colors: null,
                    background_color: undefined,
                    card_background_color: null,
                    hero_background_color: null,
                    overlay_background_color: null,
                    header_background_color: null,
                    footer_background_color: null,
                    text_primary_color: undefined,
                    text_secondary_color: undefined,
                    text_inverse_color: undefined,
                    link_color: undefined,
                    link_hover_color: undefined,
                    border_color: undefined,
                    divider_color: undefined,
                    shadow_color: null,
                    nav_text_color: undefined,
                    nav_text_color_inactive: undefined,
                    nav_hover_color: undefined,
                },
            };
            globalThis.fetch = mockFetch(minimalResponse);

            const store = useBrandingStore();
            await store.fetch();

            // Falsy-guarded fields keep their defaults when API returns null
            expect(store.primaryColor).toBe('#1976d2');
            expect(store.secondaryColor).toBe('#0d47a1');
            expect(store.typographyFontFamily).toBe('Open Sans');
            expect(store.typographyFontWeights).toEqual([400, 600, 700]);
            expect(store.cardBackgroundColor).toBe('#FFFFFF');
            expect(store.shadowColor).toBe('#000000');
        });

        it('does not crash on fetch error', async () => {
            vi.spyOn(console, 'error').mockImplementation(() => {});
            globalThis.fetch = mockFetchError('Server down');

            const store = useBrandingStore();
            await store.fetch();

            // State should remain at defaults
            expect(store.primaryColor).toBe('#1976d2');
            expect(store.secondaryColor).toBe('#0d47a1');
        });

        it('falls back to the system font stack when no custom font is provided (no third-party requests)', async () => {
            globalThis.fetch = mockFetch(fullBrandingResponse);

            const store = useBrandingStore();
            await store.fetch();

            // No Google Fonts link is appended anywhere in the document
            expect(document.querySelector('link[href*="fonts.googleapis.com"]')).toBeNull();

            // The CSS variable still carries a usable system fallback stack
            const fontFamily = document.documentElement.style.getPropertyValue('--font-family');
            expect(fontFamily).toContain('Roboto');
            expect(fontFamily).toContain('system-ui');
        });

        it('loads custom font when custom font name and file are provided', async () => {
            const customFontResponse = {
                data: {
                    ...fullBrandingResponse.data,
                    typography_custom_font_name: 'MyCustomFont',
                    typography_custom_font_file: 'https://example.com/fonts/custom.woff2',
                },
            };
            globalThis.fetch = mockFetch(customFontResponse);

            const store = useBrandingStore();
            await store.fetch();

            expect(store.customFontName).toBe('MyCustomFont');
            expect(store.customFontFile).toBe('https://example.com/fonts/custom.woff2');

            // Font family CSS variable should use custom font name
            const expectedFontFamily = "'MyCustomFont', ui-sans-serif, system-ui, sans-serif";
            expect(setPropertySpy).toHaveBeenCalledWith('--font-family', expectedFontFamily);
        });

        it('uses accent color for accent CSS variable, falling back to primary', async () => {
            const noAccentResponse = {
                data: {
                    ...fullBrandingResponse.data,
                    accent_color: null,
                },
            };
            globalThis.fetch = mockFetch(noAccentResponse);

            const store = useBrandingStore();
            await store.fetch();

            // accent_color is null, so effective accent color falls back to primaryColor
            expect(setPropertySpy).toHaveBeenCalledWith('--accent-color', '#FF0000');
        });

        it('uses link color fallback chain when link colors are not set', async () => {
            const noLinkColorResponse = {
                data: {
                    ...fullBrandingResponse.data,
                    link_color: undefined,
                    link_hover_color: undefined,
                    accent_color: '#0000FF',
                },
            };
            globalThis.fetch = mockFetch(noLinkColorResponse);

            const store = useBrandingStore();
            await store.fetch();

            // link_color falls back to accent_color
            expect(setPropertySpy).toHaveBeenCalledWith('--link-color', '#0000FF');
            // link_hover_color falls back to secondary_color
            expect(setPropertySpy).toHaveBeenCalledWith('--link-hover-color', '#00FF00');
        });
    });

    describe('font schema', () => {
        it('has default values without a font schema in the response', async () => {
            globalThis.fetch = mockFetch({ data: { primary_color: '#1976d2' } });

            const store = useBrandingStore();
            await store.fetch();

            expect(store.fontFamilyHeading).toBeNull();
            expect(store.fontFamilyBody).toBeNull();
            expect(store.fontScale).toBe('default');
            expect(store.fontFaces).toEqual([]);
            expect(setPropertySpy).toHaveBeenCalledWith('--font-size-base', '1rem');
        });

        it('sets --font-heading, --font-body and --font-size-base from the response', async () => {
            globalThis.fetch = mockFetch({
                data: {
                    primary_color: '#1976d2',
                    font_family_heading: 'Montserrat, sans-serif',
                    font_family_body: 'Open Sans, sans-serif',
                    font_scale: 'large',
                },
            });

            const store = useBrandingStore();
            await store.fetch();

            expect(store.fontFamilyHeading).toBe('Montserrat, sans-serif');
            expect(store.fontFamilyBody).toBe('Open Sans, sans-serif');
            expect(store.fontScale).toBe('large');
            expect(setPropertySpy).toHaveBeenCalledWith('--font-heading', 'Montserrat, sans-serif');
            expect(setPropertySpy).toHaveBeenCalledWith('--font-body', 'Open Sans, sans-serif');
            expect(setPropertySpy).toHaveBeenCalledWith('--font-size-base', '1.125rem');
        });

        it('injects @font-face rules for font_faces into a #theme-fonts style tag', async () => {
            globalThis.fetch = mockFetch({
                data: {
                    primary_color: '#1976d2',
                    font_faces: [
                        { family: 'House Sans', src: '/storage/fonts/custom/house-sans.woff2', weight: 400, style: 'normal' },
                        { family: 'House Sans', src: '/storage/fonts/custom/house-sans-bold.woff', weight: 700, style: 'normal' },
                    ],
                },
            });

            const store = useBrandingStore();
            await store.fetch();

            const styleEl = document.getElementById('theme-fonts');
            expect(styleEl).not.toBeNull();
            expect(styleEl.textContent).toContain('House');
            expect(styleEl.textContent).toContain('house-sans.woff2');
            expect(styleEl.textContent).toContain("format('woff2')");
            expect(styleEl.textContent).toContain('house-sans-bold.woff');
            expect(styleEl.textContent).toContain('font-weight: 700');
        });

        it('ignores font_faces entries with a disallowed file extension', async () => {
            globalThis.fetch = mockFetch({
                data: {
                    primary_color: '#1976d2',
                    font_faces: [
                        { family: 'Evil Font', src: '/storage/fonts/custom/evil.ttf', weight: 400 },
                    ],
                },
            });

            const store = useBrandingStore();
            await store.fetch();

            const styleEl = document.getElementById('theme-fonts');
            expect(styleEl.textContent).not.toContain('Evil Font');
        });

        it('drops font_faces entries that try to inject CSS via src or weight', async () => {
            globalThis.fetch = mockFetch({
                data: {
                    primary_color: '#1976d2',
                    font_faces: [
                        { family: 'Evil', src: "') } body { color: red } /* x.woff2", weight: 400 },
                        { family: 'Good', src: '/storage/fonts/good.woff2', weight: '400; } * { background: red } /*' },
                    ],
                },
            });

            const store = useBrandingStore();
            await store.fetch();

            const css = document.getElementById('theme-fonts').textContent;
            expect(css).not.toContain('Evil');
            expect(css).not.toContain('background');
            expect(css).toContain("font-family: 'Good'");
            expect(css).toContain('font-weight: 400;');
        });

        it('clears the #theme-fonts style tag when font_faces is empty', async () => {
            globalThis.fetch = mockFetch({
                data: {
                    primary_color: '#1976d2',
                    font_faces: [],
                },
            });

            const store = useBrandingStore();
            await store.fetch();

            const styleEl = document.getElementById('theme-fonts');
            expect(styleEl.textContent).toBe('');
        });
    });
});
