// Small translation map for static UI strings that don't come from the API/CMS.
const strings = {
    home: { de: 'Startseite', en: 'Home' },
    openMenu: { de: 'Menü öffnen', en: 'Open menu' },
    closeMenu: { de: 'Menü schließen', en: 'Close menu' },
    language: { de: 'Sprache', en: 'Language' },
    searchPlaceholder: { de: 'Nach Themen oder Begriffen suchen...', en: 'Search by topics or terms...' },
};

export function t(key, locale) {
    return strings[key]?.[locale] ?? strings[key]?.de ?? key;
}
