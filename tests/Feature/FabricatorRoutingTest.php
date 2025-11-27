<?php

namespace Tests\Feature;

use App\Filament\Fabricator\Layouts\LandingpageLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Page;

class FabricatorRoutingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that '/' route resolves to a Fabricator page with landingpage layout (slug 'home').
     */
    public function test_root_route_resolves_to_fabricator_landing_page_with_home_slug(): void
    {
        // Create a home page with landingpage layout
        $page = Page::create([
            'title' => ['de' => 'Home', 'en' => ''],
            'slug' => ['de' => 'home', 'en' => ''],
            'layout' => LandingpageLayout::getName(),
            'blocks' => ['de' => [], 'en' => []],
        ]);

        // Access root route
        $response = $this->get('/');

        // Assert page renders successfully
        $response->assertStatus(200);
        
        // Assert page title is present
        $response->assertSee('Home', false);
        
        // Assert landingpage layout structure is present
        $response->assertSee('header', false);
        $response->assertSee('main', false);
        $response->assertSee('footer', false);
    }

    /**
     * Test that '/' route resolves to a Fabricator page with landingpage layout (slug '/').
     */
    public function test_root_route_resolves_to_fabricator_landing_page_with_root_slug(): void
    {
        // Create a home page with landingpage layout and slug '/'
        $page = Page::create([
            'title' => ['de' => 'Home', 'en' => ''],
            'slug' => ['de' => '/', 'en' => ''],
            'layout' => LandingpageLayout::getName(),
            'blocks' => ['de' => [], 'en' => []],
        ]);

        // Access root route
        $response = $this->get('/');

        // Assert page renders successfully
        $response->assertStatus(200);
        
        // Assert page title is present
        $response->assertSee('Home', false);
        
        // Assert landingpage layout structure is present
        $response->assertSee('header', false);
        $response->assertSee('main', false);
        $response->assertSee('footer', false);
    }

    /**
     * Test that '/' route prioritizes slug '/' over 'home'.
     */
    public function test_root_route_prioritizes_root_slug_over_home(): void
    {
        // Create both pages
        $homePage = Page::create([
            'title' => ['de' => 'Home Page', 'en' => ''],
            'slug' => ['de' => 'home', 'en' => ''],
            'layout' => LandingpageLayout::getName(),
            'blocks' => ['de' => [], 'en' => []],
        ]);

        $rootPage = Page::create([
            'title' => ['de' => 'Root Page', 'en' => ''],
            'slug' => ['de' => '/', 'en' => ''],
            'layout' => LandingpageLayout::getName(),
            'blocks' => ['de' => [], 'en' => []],
        ]);

        // Access root route
        $response = $this->get('/');

        // Assert page renders successfully
        $response->assertStatus(200);
        
        // Assert root page title is present (should prioritize '/' over 'home')
        // Note: whereIn returns first match, so order matters - we check for both
        $response->assertSee('Root Page', false);
    }

    /**
     * Test that '/' falls back to Vue SPA if no Fabricator home page exists.
     */
    public function test_root_route_falls_back_to_vue_spa_if_no_fabricator_page(): void
    {
        // Don't create any Fabricator page
        
        // Access root route
        $response = $this->get('/');

        // Should redirect to /app
        $response->assertRedirect('/app');

        // Follow redirect and assert Vue SPA view
        $spaResponse = $this->get('/app');
        $spaResponse->assertStatus(200);
        $spaResponse->assertSee('<div id="app"></div>', false);
    }
}

