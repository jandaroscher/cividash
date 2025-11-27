<?php

namespace Tests\Feature;

use App\Filament\Fabricator\Layouts\LandingpageLayout;
use App\Filament\Fabricator\Layouts\SubpageLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\Page;

class FabricatorLayoutRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create fake storage disk for testing
        Storage::fake('public');
    }

    /**
     * Test that landingpage layout renders correctly with header, content and footer.
     */
    public function test_landingpage_layout_renders_correctly(): void
    {
        // Set locale for consistent test results
        app()->setLocale('de');
        
        // Create a test page with landingpage layout using translatable format
        $page = Page::create([
            'title' => ['de' => 'Test Landing Page', 'en' => ''],
            'slug' => ['de' => 'test-landing', 'en' => ''],
            'layout' => LandingpageLayout::getName(),
            'blocks' => ['de' => [], 'en' => []],
        ]);

        // Render the page
        $response = $this->get('/test-landing');

        // Assert page renders successfully
        $response->assertStatus(200);
        
        // Assert page title is in the HTML (in title tag or body)
        $response->assertSee('Test Landing Page', false);
        
        // Assert layout structure is present (header/main/footer)
        $response->assertSee('header', false);
        $response->assertSee('main', false);
        $response->assertSee('footer', false);
    }

    /**
     * Test that subpage layout renders correctly with header, content and footer.
     */
    public function test_subpage_layout_renders_correctly(): void
    {
        // Set locale for consistent test results
        app()->setLocale('de');
        
        // Create a test page with subpage layout using translatable format
        $page = Page::create([
            'title' => ['de' => 'Test Subpage', 'en' => ''],
            'slug' => ['de' => 'test-subpage', 'en' => ''],
            'layout' => SubpageLayout::getName(),
            'blocks' => ['de' => [], 'en' => []],
        ]);

        // Render the page
        $response = $this->get('/test-subpage');

        // Assert page renders successfully
        $response->assertStatus(200);
        
        // Assert page title is in the HTML
        $response->assertSee('Test Subpage', false);
        
        // Assert layout structure is present (header/main/footer)
        $response->assertSee('header', false);
        $response->assertSee('main', false);
        $response->assertSee('footer', false);
    }
}

