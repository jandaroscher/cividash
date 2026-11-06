// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

// Thin wrapper around console.error/warn that is a no-op in production builds.
export function logError(...args) {
    // eslint-disable-next-line no-console
    if (!import.meta.env.PROD) console.error(...args);
}

export function logWarn(...args) {
    // eslint-disable-next-line no-console
    if (!import.meta.env.PROD) console.warn(...args);
}
