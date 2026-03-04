import { defineStore } from 'pinia';
import { getApiBaseUrl } from '../utils/api';

export const useBrandingStore = defineStore('branding', {
    state: () => ({
        primaryColor: '#1976d2',
        secondaryColor: '#0d47a1',
        logoUrl: null,
        accentColor: null,
        typographyFontFamily: 'Open Sans',
        typographyFontWeights: [400, 600, 700],
        typographyFontSizes: {
            base: '1rem',
            small: '0.875rem',
            large: '1.125rem',
            h1: '3rem',
            h2: '2.25rem',
            h3: '1.875rem',
            h4: '1.5rem',
            h5: '1.25rem',
            h6: '1.125rem',
        },
        customFontName: null,
        customFontFile: null,
        sliderColors: {
            rail: '#191919',
            handle: null,
            handleBorder: '#191919',
        },
        backgroundColor: null,
        cardBackgroundColor: '#FFFFFF',
        heroBackgroundColor: '#111827',
        overlayBackgroundColor: 'rgba(0,0,0,0.4)',
        headerBackgroundColor: '#FFFFFF',
        footerBackgroundColor: '#E5E7EB',
        textPrimaryColor: null,
        textSecondaryColor: null,
        textInverseColor: null,
        linkColor: null,
        linkHoverColor: null,
        borderColor: null,
        dividerColor: null,
        shadowColor: '#000000',
        navTextColor: '#374151',
        navTextColorInactive: '#9CA3AF',
        navHoverColor: '#FCA5A5',
    }),
    actions: {
        async fetch() {
            try {
                const apiUrl = getApiBaseUrl();
                const res = await fetch(
                    apiUrl + '/api/config/branding',
                    { credentials: 'include' },
                );
                const json = await res.json();

                const {
                    primary_color,
                    secondary_color,
                    logo_url,
                    accent_color,
                    typography_font_family,
                    typography_font_weights,
                    typography_font_sizes,
                    typography_custom_font_name,
                    typography_custom_font_file,
                    slider_colors,
                    background_color,
                    card_background_color,
                    hero_background_color,
                    overlay_background_color,
                    header_background_color,
                    footer_background_color,
                    text_primary_color,
                    text_secondary_color,
                    text_inverse_color,
                    link_color,
                    link_hover_color,
                    border_color,
                    divider_color,
                    shadow_color,
                    nav_text_color,
                    nav_text_color_inactive,
                    nav_hover_color,
                } = json.data;

                this.primaryColor = primary_color || this.primaryColor;
                this.secondaryColor = secondary_color || this.secondaryColor;
                this.logoUrl = logo_url;
                this.accentColor = accent_color;
                this.typographyFontFamily = typography_font_family || this.typographyFontFamily;
                this.typographyFontWeights = typography_font_weights || this.typographyFontWeights;
                
                // Update font sizes if provided
                if (typography_font_sizes) {
                    this.typographyFontSizes = { ...this.typographyFontSizes, ...typography_font_sizes };
                }
                
                // Update custom font if provided
                this.customFontName = typography_custom_font_name !== undefined ? typography_custom_font_name : this.customFontName;
                this.customFontFile = typography_custom_font_file !== undefined ? typography_custom_font_file : this.customFontFile;

                // Update slider colors if provided
                if (slider_colors) {
                    this.sliderColors = { ...this.sliderColors, ...slider_colors };
                }

                // Update background colors if provided
                this.backgroundColor = background_color !== undefined ? background_color : this.backgroundColor;
                this.cardBackgroundColor = card_background_color || this.cardBackgroundColor;
                this.heroBackgroundColor = hero_background_color || this.heroBackgroundColor;
                this.overlayBackgroundColor = overlay_background_color || this.overlayBackgroundColor;
                this.headerBackgroundColor = header_background_color || this.headerBackgroundColor;
                this.footerBackgroundColor = footer_background_color || this.footerBackgroundColor;

                // Update text colors if provided
                this.textPrimaryColor = text_primary_color !== undefined ? text_primary_color : this.textPrimaryColor;
                this.textSecondaryColor = text_secondary_color !== undefined ? text_secondary_color : this.textSecondaryColor;
                this.textInverseColor = text_inverse_color !== undefined ? text_inverse_color : this.textInverseColor;
                this.linkColor = link_color !== undefined ? link_color : this.linkColor;
                this.linkHoverColor = link_hover_color !== undefined ? link_hover_color : this.linkHoverColor;
                this.borderColor = border_color !== undefined ? border_color : this.borderColor;
                this.dividerColor = divider_color !== undefined ? divider_color : this.dividerColor;
                this.shadowColor = shadow_color || this.shadowColor;
                this.navTextColor = nav_text_color !== undefined ? nav_text_color : this.navTextColor;
                this.navTextColorInactive = nav_text_color_inactive !== undefined ? nav_text_color_inactive : this.navTextColorInactive;
                this.navHoverColor = nav_hover_color !== undefined ? nav_hover_color : this.navHoverColor;

                // Load font (Google Font or Custom Font)
                if (this.customFontFile && this.customFontName) {
                    this.loadCustomFont(this.customFontName, this.customFontFile);
                } else {
                    this.loadGoogleFont(this.typographyFontFamily, this.typographyFontWeights);
                }

                // Set CSS variables
                document.documentElement.style.setProperty('--primary-color', this.primaryColor || '#1976d2');
                document.documentElement.style.setProperty('--secondary-color', this.secondaryColor || '#0d47a1');
                
                // Set accent color (use accent_color if provided, otherwise fallback to primary_color or default)
                const effectiveAccentColor = this.accentColor || this.primaryColor || '#E30613';
                document.documentElement.style.setProperty('--accent-color', effectiveAccentColor);
                document.documentElement.style.setProperty('--accent-color-dark', this.secondaryColor || '#891F00');

                // Set typography CSS variables (both --font-family and --font-sans for Tailwind compatibility)
                const effectiveFontFamily = this.customFontName || this.typographyFontFamily;
                const fontFamilyValue = `'${effectiveFontFamily}', ui-sans-serif, system-ui, sans-serif`;
                document.documentElement.style.setProperty('--font-family', fontFamilyValue);
                document.documentElement.style.setProperty('--font-sans', fontFamilyValue);

                // Set font size CSS variables
                if (this.typographyFontSizes) {
                    Object.keys(this.typographyFontSizes).forEach(key => {
                        document.documentElement.style.setProperty(`--font-size-${key}`, this.typographyFontSizes[key]);
                    });
                }

                // Set slider colors as CSS variables
                document.documentElement.style.setProperty('--slider-rail-color', this.sliderColors.rail || '#191919');
                document.documentElement.style.setProperty('--slider-handle-color', this.sliderColors.handle || effectiveAccentColor);
                document.documentElement.style.setProperty('--slider-handle-border-color', this.sliderColors.handleBorder || '#191919');

                // Set background colors as CSS variables
                if (this.backgroundColor) {
                    document.documentElement.style.setProperty('--background-color', this.backgroundColor);
                }
                document.documentElement.style.setProperty('--card-background-color', this.cardBackgroundColor || '#FFFFFF');
                document.documentElement.style.setProperty('--hero-background-color', this.heroBackgroundColor || '#111827');
                document.documentElement.style.setProperty('--overlay-background-color', this.overlayBackgroundColor || 'rgba(0,0,0,0.4)');
                document.documentElement.style.setProperty('--header-background-color', this.headerBackgroundColor || '#FFFFFF');
                document.documentElement.style.setProperty('--footer-background-color', this.footerBackgroundColor || '#E5E7EB');

                // Set text colors as CSS variables
                if (this.textPrimaryColor) {
                    document.documentElement.style.setProperty('--text-primary-color', this.textPrimaryColor);
                }
                if (this.textSecondaryColor) {
                    document.documentElement.style.setProperty('--text-secondary-color', this.textSecondaryColor);
                }
                if (this.textInverseColor) {
                    document.documentElement.style.setProperty('--text-inverse-color', this.textInverseColor);
                }
                // Link colors (fallback to accent colors if not set)
                const effectiveLinkColor = this.linkColor || this.accentColor || this.primaryColor || '#E30613';
                const effectiveLinkHoverColor = this.linkHoverColor || this.secondaryColor || '#891F00';
                document.documentElement.style.setProperty('--link-color', effectiveLinkColor);
                document.documentElement.style.setProperty('--link-hover-color', effectiveLinkHoverColor);

                // Set border and shadow colors as CSS variables
                if (this.borderColor) {
                    document.documentElement.style.setProperty('--border-color', this.borderColor);
                }
                if (this.dividerColor) {
                    document.documentElement.style.setProperty('--divider-color', this.dividerColor);
                }
                document.documentElement.style.setProperty('--shadow-color', this.shadowColor || '#000000');
                
                // Set navigation colors as CSS variables
                document.documentElement.style.setProperty('--nav-text-color', this.navTextColor || '#374151');
                document.documentElement.style.setProperty('--nav-text-color-inactive', this.navTextColorInactive || '#9CA3AF');
                document.documentElement.style.setProperty('--nav-hover-color', this.navHoverColor || '#FCA5A5');
            } catch (error) {
                logError('Failed to fetch branding settings:', error);
            }
        },
        loadGoogleFont(fontFamily, weights = [400, 600, 700]) {
            // Check if font is already loaded
            const fontId = `google-font-${fontFamily.replace(/\s+/g, '-').toLowerCase()}`;
            if (document.getElementById(fontId)) {
                return;
            }

            // Create Google Fonts URL
            const weightsParam = weights.join(';');
            const fontName = fontFamily.replace(/\s+/g, '+');
            const fontUrl = `https://fonts.googleapis.com/css2?family=${fontName}:wght@${weightsParam}&display=swap`;

            // Create and append link element
            const link = document.createElement('link');
            link.id = fontId;
            link.rel = 'stylesheet';
            link.href = fontUrl;
            document.head.appendChild(link);
        },
        loadCustomFont(fontName, fontFileUrl) {
            // Validate and sanitize inputs
            if (!fontName || !fontFileUrl) {
                logError('Invalid font name or URL provided');
                return;
            }

            // Escape font name to prevent CSS injection
            const escapedFontName = CSS.escape(fontName);

            // Validate URL protocol and extension
            const allowedProtocols = ['https:', 'http:', 'data:'];
            const allowedExtensions = ['woff2', 'woff', 'ttf', 'otf'];
            
            let validatedUrl;
            try {
                const url = new URL(fontFileUrl, window.location.origin);
                
                // Check protocol
                if (!allowedProtocols.includes(url.protocol)) {
                    logError(`Invalid URL protocol: ${url.protocol}. Only https://, http://, or data: are allowed.`);
                    return;
                }
                
                // Check file extension
                const pathname = url.pathname || '';
                const ext = pathname.split('.').pop()?.toLowerCase() || '';
                if (!allowedExtensions.includes(ext)) {
                    logError(`Invalid file extension: ${ext}. Only ${allowedExtensions.join(', ')} are allowed.`);
                    return;
                }
                
                // Use the validated URL
                validatedUrl = url.href;
            } catch (e) {
                // If URL constructor fails, try to validate as relative path
                const ext = fontFileUrl.split('.').pop()?.toLowerCase() || '';
                if (!allowedExtensions.includes(ext)) {
                    logError(`Invalid file extension: ${ext}. Only ${allowedExtensions.join(', ')} are allowed.`);
                    return;
                }
                // For relative paths, encode the URL safely
                validatedUrl = encodeURI(fontFileUrl);
            }

            // Determine font format from file extension
            const getFontFormat = (url) => {
                try {
                    const urlObj = new URL(url, window.location.origin);
                    const ext = urlObj.pathname.split('.').pop()?.toLowerCase() || '';
                    const formatMap = {
                        'woff2': 'woff2',
                        'woff': 'woff',
                        'ttf': 'truetype',
                        'otf': 'opentype',
                    };
                    return formatMap[ext] || 'woff2';
                } catch {
                    // Fallback for relative URLs
                    const ext = url.split('.').pop()?.toLowerCase() || '';
                    const formatMap = {
                        'woff2': 'woff2',
                        'woff': 'woff',
                        'ttf': 'truetype',
                        'otf': 'opentype',
                    };
                    return formatMap[ext] || 'woff2';
                }
            };

            const fontFormat = getFontFormat(validatedUrl);

            // Check if custom font is already loaded
            const fontId = `custom-font-${escapedFontName.replace(/\s+/g, '-').toLowerCase()}`;
            if (document.getElementById(fontId)) {
                return;
            }

            // Create @font-face rule using CSSStyleSheet.insertRule for safer injection
            const style = document.createElement('style');
            style.id = fontId;
            document.head.appendChild(style);
            
            try {
                const sheet = style.sheet;
                if (sheet) {
                    // Use insertRule with escaped values to prevent CSS injection
                    const rule = `@font-face { font-family: '${escapedFontName}'; src: url('${validatedUrl}') format('${fontFormat}'); font-display: swap; }`;
                    sheet.insertRule(rule, 0);
                } else {
                    // Fallback for browsers that don't support sheet immediately
                    style.textContent = `@font-face { font-family: '${escapedFontName}'; src: url('${validatedUrl}') format('${fontFormat}'); font-display: swap; }`;
                }
            } catch (error) {
                logError('Failed to load custom font:', error);
                // Remove the style element if insertion failed
                style.remove();
            }
        },
    },
});

