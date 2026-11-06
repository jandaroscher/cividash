// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

/**
 * Get the API base URL with SSR guard
 * @returns {string} API base URL or empty string if not available
 */
export function getApiBaseUrl() {
    return typeof window !== 'undefined' ? window.location.origin : '';
}
