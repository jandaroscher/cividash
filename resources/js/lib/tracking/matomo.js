// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

/**
 * Matomo adapter. Privacy defaults so no consent banner is needed: no cookies, no
 * browser feature detection (device access under TTDSG/ePrivacy), Do Not Track
 * respected. IP anonymisation is a setting of the Matomo instance.
 */
export function createMatomoTracker({ url, site_id: siteId }) {
    window._paq = window._paq || [];
    const paq = window._paq;

    paq.push(['disableCookies']);
    paq.push(['disableBrowserFeatureDetection']);
    paq.push(['setDoNotTrack', true]);
    paq.push(['enableLinkTracking']);
    paq.push(['setTrackerUrl', `${url}matomo.php`]);
    paq.push(['setSiteId', siteId]);

    const script = document.createElement('script');
    script.async = true;
    script.src = `${url}matomo.js`;
    document.head.appendChild(script);

    // On a full page load the browser referrer may be our own URL with its query string.
    const referrer = document.referrer ? new URL(document.referrer) : null;
    let previousUrl = referrer?.origin === window.location.origin ? referrer.origin + referrer.pathname : null;

    return {
        trackPageView(pageUrl, title) {
            if (previousUrl) {
                paq.push(['setReferrerUrl', previousUrl]);
            }
            paq.push(['setCustomUrl', pageUrl]);
            paq.push(['setDocumentTitle', title]);
            paq.push(['trackPageView']);
            previousUrl = pageUrl;
        },
    };
}
