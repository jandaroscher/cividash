<?php

namespace Tests\Feature\Theme;

use App\Exceptions\ThemeInUseException;
use App\Models\Tenant;
use App\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeDeleteGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_theme_assigned_to_a_tenant_is_rejected(): void
    {
        $theme = Theme::factory()->create();
        Tenant::factory()->create(['theme_id' => $theme->id]);

        $this->expectException(ThemeInUseException::class);

        $theme->delete();
    }

    public function test_deleting_an_unused_theme_succeeds(): void
    {
        $theme = Theme::factory()->create();

        $theme->delete();

        $this->assertNull(Theme::find($theme->id));
    }
}
