<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FilamentFabricatorInstallationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that Fabricator tables exist after migration.
     */
    public function test_fabricator_tables_exist_after_migration(): void
    {
        // Run migrations
        $this->artisan('migrate')->assertSuccessful();

        // Check that the pages table exists (default Fabricator table)
        $this->assertTrue(
            Schema::hasTable(config('filament-fabricator.table_name', 'pages')),
            'Fabricator pages table should exist after migration'
        );

        // Verify table structure
        $this->assertTrue(
            Schema::hasColumns(config('filament-fabricator.table_name', 'pages'), [
                'id',
                'title',
                'slug',
                'layout',
                'blocks',
                'parent_id',
                'created_at',
                'updated_at',
            ]),
            'Fabricator pages table should have all required columns'
        );
    }
}





