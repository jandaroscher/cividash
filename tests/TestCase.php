<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Set up the test environment.
     * Creates a default tenant if it doesn't exist to prevent InvalidTenantContextException
     * when tests create tenant-scoped models without explicit tenant context.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create default tenant if it doesn't exist
        // This prevents InvalidTenantContextException when tests create models
        // without explicit tenant context
        if (! Tenant::where('slug', 'default')->exists()) {
            Tenant::create([
                'name' => 'Default Tenant',
                'slug' => 'default',
            ]);
        }
    }
}
