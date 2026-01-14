<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    /**
     * Prepare the test environment and ensure a default tenant exists for tenant-scoped tests.
     *
     * If the test uses the database (RefreshDatabase trait) and the `tenants` table exists,
     * creates a Tenant with name "Default Tenant" and slug "default" when one is not present.
     * Database-related errors are ignored so tests can run without a full database setup.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Only run when a DB is available (when RefreshDatabase is used)
        if ($this->usesDatabase()) {
            try {
                // Check if tenants table exists before trying to create a tenant
                if (Schema::hasTable('tenants') && ! Tenant::where('slug', 'default')->exists()) {
                    Tenant::create([
                        'name' => 'Default Tenant',
                        'slug' => 'default',
                    ]);
                }
            } catch (\Throwable $e) {
                // Ignore if the table is missing or other DB errors occur
                // This allows tests to run without a full database setup
            }
        }
    }

    /**
     * Determine whether the test class uses the RefreshDatabase trait.
     *
     * @return bool `true` if the test class uses the RefreshDatabase trait, `false` otherwise.
     */
    protected function usesDatabase(): bool
    {
        return in_array(RefreshDatabase::class, class_uses_recursive(static::class));
    }
}