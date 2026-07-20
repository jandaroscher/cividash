/**
 * Convert a hex color string to an rgba() string with the given alpha.
 *
 * Supports 3- and 6-digit hex (with or without leading `#`). Falls back to a
 * neutral grey when the input cannot be parsed, so callers can pass
 * tenant-configurable branding colors without guarding every call site.
 *
 * @param {string} hex - Hex color, e.g. "#E30613" or "E30613".
 * @param {number} alpha - Alpha channel between 0 and 1.
 * @returns {string} An `rgba(r, g, b, a)` string.
 */
export function hexToRgba(hex, alpha = 1) {
    const normalized = (hex || '').replace('#', '');
    const expanded = normalized.length === 3
        ? normalized.split('').map((char) => char + char).join('')
        : normalized;

    const isValid = /^[0-9a-fA-F]{6}$/.test(expanded);
    const r = isValid ? parseInt(expanded.slice(0, 2), 16) : 155;
    const g = isValid ? parseInt(expanded.slice(2, 4), 16) : 155;
    const b = isValid ? parseInt(expanded.slice(4, 6), 16) : 155;

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}
