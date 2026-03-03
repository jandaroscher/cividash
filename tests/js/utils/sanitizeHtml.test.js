import { describe, it, expect, beforeEach } from 'vitest';
import { sanitizeHtml, isExternalUrl } from '@/utils/sanitizeHtml';

describe('isExternalUrl', () => {
    it('returns false for null/undefined/empty', () => {
        expect(isExternalUrl(null)).toBe(false);
        expect(isExternalUrl(undefined)).toBe(false);
        expect(isExternalUrl('')).toBe(false);
    });

    it('returns false for hash anchors', () => {
        expect(isExternalUrl('#section')).toBe(false);
        expect(isExternalUrl('#')).toBe(false);
    });

    it('returns false for relative paths', () => {
        expect(isExternalUrl('/about')).toBe(false);
        expect(isExternalUrl('/en/about')).toBe(false);
        expect(isExternalUrl('/some/deep/path')).toBe(false);
    });

    it('returns false for mailto links', () => {
        expect(isExternalUrl('mailto:test@example.com')).toBe(false);
    });

    it('returns false for tel links', () => {
        expect(isExternalUrl('tel:+49123456')).toBe(false);
    });

    it('returns false for same-hostname URLs', () => {
        // window.location.hostname is 'localhost' in happy-dom
        expect(isExternalUrl('http://localhost/page')).toBe(false);
        expect(isExternalUrl('https://localhost/page')).toBe(false);
    });

    it('returns true for external URLs', () => {
        expect(isExternalUrl('https://google.com')).toBe(true);
        expect(isExternalUrl('http://example.org/path')).toBe(true);
        expect(isExternalUrl('https://other-domain.de/about')).toBe(true);
    });

    it('returns false for non-http protocols', () => {
        expect(isExternalUrl('ftp://files.example.com')).toBe(false);
        expect(isExternalUrl('javascript:void(0)')).toBe(false);
    });

    it('returns false for malformed URLs', () => {
        expect(isExternalUrl('not a url at all')).toBe(false);
    });
});

describe('sanitizeHtml', () => {
    it('sanitizes HTML and returns a string', () => {
        const result = sanitizeHtml('<p>Hello</p>');
        expect(result).toBe('<p>Hello</p>');
    });

    it('adds target and rel to external links', () => {
        const html = '<a href="https://google.com">Google</a>';
        const result = sanitizeHtml(html);

        expect(result).toContain('target="_blank"');
        expect(result).toContain('rel="noopener noreferrer"');
    });

    it('does NOT add target/rel to internal links', () => {
        const html = '<a href="/about">About</a>';
        const result = sanitizeHtml(html);

        expect(result).not.toContain('target=');
        expect(result).not.toContain('rel=');
    });

    it('does NOT add target/rel to relative links', () => {
        const html = '<a href="/en/contact">Contact</a>';
        const result = sanitizeHtml(html);

        expect(result).not.toContain('target=');
        expect(result).not.toContain('rel=');
    });

    it('does NOT add target/rel to hash anchors', () => {
        const html = '<a href="#top">Top</a>';
        const result = sanitizeHtml(html);

        expect(result).not.toContain('target=');
        expect(result).not.toContain('rel=');
    });

    it('does NOT add target/rel to mailto links', () => {
        const html = '<a href="mailto:info@example.com">Email</a>';
        const result = sanitizeHtml(html);

        expect(result).not.toContain('target=');
        expect(result).not.toContain('rel=');
    });

    it('handles mixed internal and external links', () => {
        const html = '<a href="/about">About</a> <a href="https://external.com">Ext</a>';
        const result = sanitizeHtml(html);

        // Parse the result to check individual links
        const div = document.createElement('div');
        div.innerHTML = result;
        const links = div.querySelectorAll('a');

        // Internal link should not have target
        expect(links[0].getAttribute('target')).toBeNull();
        expect(links[0].getAttribute('rel')).toBeNull();

        // External link should have target="_blank"
        expect(links[1].getAttribute('target')).toBe('_blank');
        expect(links[1].getAttribute('rel')).toBe('noopener noreferrer');
    });

    it('strips dangerous HTML', () => {
        const html = '<script>alert("xss")</script><p>Safe</p>';
        const result = sanitizeHtml(html);

        expect(result).not.toContain('<script>');
        expect(result).toContain('<p>Safe</p>');
    });

    it('returns empty string for empty input', () => {
        expect(sanitizeHtml('')).toBe('');
    });
});
