<?php

namespace Tests\Unit\Services\Integration;

use Tests\TestCase;

class CivitasConfigTest extends TestCase
{
    public function test_driver_defaults_to_ngsi_ld(): void
    {
        $this->assertSame('ngsi-ld', config('integrations.civitas.driver'));
    }

    public function test_context_url_key_exists(): void
    {
        $this->assertArrayHasKey('context_url', config('integrations.civitas'));
    }

    public function test_prune_removed_defaults_to_false(): void
    {
        $this->assertFalse(config('integrations.civitas.sync.prune_removed'));
    }

    public function test_api_url_default_uses_stellio_ngsi_ld_path(): void
    {
        // CORE V1.6.2 exposes NGSI-LD via Stellio at /context/ngsi-ld,
        // not the legacy /ngsi-ld/v1 path. Default must reflect this.
        // No CIVITAS_API_URL env override is set in the test environment,
        // so config() resolves to the hard-coded default.
        $this->assertSame(
            'http://localhost:8080/context/ngsi-ld',
            config('integrations.civitas.api_url'),
        );
    }
}
