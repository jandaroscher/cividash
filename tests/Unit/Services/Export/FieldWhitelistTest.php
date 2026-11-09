<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Unit\Services\Export;

use App\Services\Export\FieldWhitelist;
use PHPUnit\Framework\TestCase;

class FieldWhitelistTest extends TestCase
{
    public function test_defaults_contain_every_whitelisted_field(): void
    {
        $this->assertSame(FieldWhitelist::all(), FieldWhitelist::defaults());
    }

    public function test_filter_drops_unknown_fields_and_preserves_order(): void
    {
        $filtered = FieldWhitelist::filter([
            'metric.label',
            'bogus',
            'tile.title',
            'not-a-field',
            'tile.id',
        ]);

        // Canonical order from FieldWhitelist::all() must be preserved.
        $this->assertSame(
            ['tile.id', 'tile.title', 'metric.label'],
            $filtered,
        );
    }

    public function test_filter_with_no_valid_fields_returns_empty_array(): void
    {
        $this->assertSame([], FieldWhitelist::filter(['not-a-field', 'also-bad']));
    }

    public function test_rejected_returns_only_unknown_fields(): void
    {
        $rejected = FieldWhitelist::rejected([
            'tile.title',
            'bogus',
            'metric.source',
            'nope',
        ]);

        $this->assertSame(['bogus', 'nope'], $rejected);
    }

    public function test_all_returns_canonical_field_set_in_order(): void
    {
        // Internal config/sort/linking fields are not exported (tile.position,
        // category.keys, metric.indicator_type, value.sort_order); metric.key
        // is, because the importer groups values by it.
        $this->assertSame([
            'tile.id',
            'tile.slug',
            'tile.title',
            'tile.description',
            'tile.hint',
            'category.labels',
            'category.groups',
            'metric.key',
            'metric.label',
            'metric.unit',
            'value.year',
            'value.period_key',
            'value.granularity',
            'value.value',
            'metric.source',
            'metric.source_url',
            'metric.methodology',
            'metric.formula',
        ], FieldWhitelist::all());
    }

    public function test_dropped_internal_fields_are_not_whitelisted(): void
    {
        $all = FieldWhitelist::all();

        foreach (['tile.position', 'category.keys', 'metric.indicator_type', 'value.sort_order'] as $dropped) {
            $this->assertNotContains($dropped, $all);
        }
    }

    public function test_reserved_placeholder_fields_are_whitelisted(): void
    {
        $all = FieldWhitelist::all();
        $this->assertContains('metric.source', $all);
        $this->assertContains('metric.source_url', $all);
        $this->assertContains('metric.methodology', $all);
        $this->assertContains('metric.formula', $all);
    }
}
