<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentFabricatorPluginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the admin panel is accessible and contains Fabricator Pages resource.
     */
    public function test_admin_panel_contains_fabricator_pages_resource(): void
    {
        // Create a test user
        $user = User::factory()->create();

        // Authenticate and access admin panel
        $response = $this->actingAs($user)->get('/admin');

        // Assert admin panel is accessible
        $response->assertStatus(200);

        // Assert that the response contains "Seiten" (German navigation label for Fabricator Pages resource)
        // The PageResource uses getNavigationLabel() which returns "Seiten"
        $response->assertSee('Seiten', false);
    }

    /**
     * Test that Fabricator plugin is registered in the panel.
     */
    public function test_fabricator_plugin_is_registered(): void
    {
        $panel = \Filament\Facades\Filament::getPanel('admin');
        
        $this->assertNotNull($panel, 'Admin panel should exist');
        
        // Check if FilamentFabricatorPlugin is registered
        $plugins = $panel->getPlugins();
        $fabricatorPluginFound = false;
        
        foreach ($plugins as $plugin) {
            if (str_contains(get_class($plugin), 'FilamentFabricator')) {
                $fabricatorPluginFound = true;
                break;
            }
        }
        
        $this->assertTrue(
            $fabricatorPluginFound,
            'FilamentFabricatorPlugin should be registered in the admin panel'
        );
    }
}






