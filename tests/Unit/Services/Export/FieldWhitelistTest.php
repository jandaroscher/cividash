<?php

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
            'metric.key',
            'bogus',
            'tile.title',
            'not-a-field',
            'tile.id',
        ]);

        // Canonical order from FieldWhitelist::all() must be preserved.
        $this->assertSame(
            ['tile.id', 'tile.title', 'metric.key'],
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

    public function test_reserved_placeholder_fields_are_whitelisted(): void
    {
        $all = FieldWhitelist::all();
        $this->assertContains('metric.source', $all);
        $this->assertContains('metric.source_url', $all);
        $this->assertContains('metric.methodology', $all);
        $this->assertContains('metric.formula', $all);
    }
}
