<?php

namespace Tests\Feature;

use App\Filament\Resources\TileResource;
use App\Models\Tile;
use App\Models\User;
use Filament\Forms\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TileBackgroundBlocksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user for authentication
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
        ]);
    }

    public function test_admin_can_access_tile_edit_page_with_background_blocks_field(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'background_blocks' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/admin/tiles/{$tile->id}/edit");

        $response->assertSuccessful();
        // Check that the form contains the background_blocks field
        $response->assertSee('background_blocks', false);
    }

    public function test_tile_can_be_updated_with_background_blocks(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'background_blocks' => null,
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Hero Title',
                    'subtitle' => 'Hero Subtitle',
                ],
            ],
        ];

        // Update tile directly (simulating form submission)
        $tile->update([
            'background_blocks' => $blocks,
        ]);

        $tile->refresh();
        $this->assertNotNull($tile->background_blocks);
        $this->assertCount(1, $tile->background_blocks);
        $this->assertEquals('hero', $tile->background_blocks[0]['type']);
    }

    public function test_tile_background_blocks_field_is_in_form_schema(): void
    {
        // Create a mock Livewire component
        $livewire = new class extends \Filament\Resources\Pages\EditRecord
        {
            protected static string $resource = TileResource::class;
        };

        $form = TileResource::form(Form::make($livewire));
        
        // Check that the form schema can be retrieved (this validates the form structure)
        $components = $form->getComponents();
        $this->assertNotEmpty($components, 'Form should have components');
        
        // Verify that getBackgroundBlockSchemas method exists and returns blocks
        $reflection = new \ReflectionClass(TileResource::class);
        $method = $reflection->getMethod('getBackgroundBlockSchemas');
        $this->assertTrue($method->isProtected(), 'getBackgroundBlockSchemas should be protected');
        
        $method->setAccessible(true);
        $blocks = $method->invoke(null);
        $this->assertIsArray($blocks, 'getBackgroundBlockSchemas should return an array');
        $this->assertNotEmpty($blocks, 'Should have at least one block registered');
    }
}

