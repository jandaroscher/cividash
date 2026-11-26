// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

// Loads the <dotlottie-wc> custom element on first use and points its WASM
// renderer at our self-hosted copy instead of the jsdelivr/unpkg CDN default
// (no third-party hosts at runtime, see tests/Feature/Privacy/NoThirdPartyHostsTest.php).
// Loaded lazily so pages without animations skip the player bundle. Render a
// <dotlottie-wc> only after the returned promise resolves: the import defines
// the element, and an element already in the DOM would upgrade and fetch the
// CDN WASM before setWasmUrl() runs.
import dotlottieWasmUrl from '@lottiefiles/dotlottie-web/dotlottie-player.wasm?url';

let loading;

export function loadDotlottie() {
    loading ??= import('@lottiefiles/dotlottie-wc').then(({ setWasmUrl }) => {
        setWasmUrl(dotlottieWasmUrl);
    });
    return loading;
}
