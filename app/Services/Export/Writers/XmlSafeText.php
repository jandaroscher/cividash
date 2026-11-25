<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Services\Export\Writers;

/**
 * XML 1.0 forbids most control characters and U+FFFE/U+FFFF. XMLWriter and
 * OpenSpout pass some of them through verbatim, which leaves the XML file
 * or the workbook unparseable.
 */
final class XmlSafeText
{
    public static function clean(string $value): string
    {
        $value = mb_scrub($value, 'UTF-8');

        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';
    }
}
