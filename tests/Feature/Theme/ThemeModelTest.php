<?php

namespace Tests\Feature\Theme;

use App\Models\Theme;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_can_be_created_with_settings_payload(): void
    {
        $theme = Theme::create([
            'name' => 'Demo City',
            'slug' => 'demo-city',
            'settings' => ['branding' => ['accent_color' => '#d00000']],
        ]);

        $theme->refresh();

        $this->assertSame('Demo City', $theme->name);
        $this->assertIsArray($theme->settings);
        $this->assertSame('#d00000', $theme->settings['branding']['accent_color']);
    }

    public function test_slug_must_be_unique(): void
    {
        Theme::create(['name' => 'Demo City', 'slug' => 'demo-city']);

        $this->expectException(QueryException::class);

        Theme::create(['name' => 'Demo City Zwei', 'slug' => 'demo-city']);
    }

    public function test_parent_theme_id_is_nullable_and_can_reference_another_theme(): void
    {
        $orphan = Theme::create(['name' => 'Standalone', 'slug' => 'standalone']);
        $this->assertNull($orphan->parent_theme_id);

        $parent = Theme::create(['name' => 'Base', 'slug' => 'base']);
        $child = Theme::create(['name' => 'Child', 'slug' => 'child', 'parent_theme_id' => $parent->id]);

        $this->assertSame($parent->id, $child->parent_theme_id);
        $this->assertSame($parent->id, $child->parent->id);
    }
}
