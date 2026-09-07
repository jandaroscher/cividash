<?php

namespace Tests\Feature;

use App\Filament\Resources\TileResource;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
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

        // Set tenant context for tests
        $this->tenant = Tenant::where('slug', 'default')->first();
        if ($this->tenant) {
            $this->user->tenants()->sync([$this->tenant->id]);
            Filament::auth()->login($this->user);
            Filament::setTenant($this->tenant);
        }
    }

    public function test_admin_can_access_tile_edit_page_with_background_blocks_field(): void
    {
        $tenant = $this->tenant;
        $this->assertNotNull($tenant, 'Default tenant must exist for this test');

        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'background_blocks' => null,
            'tenant_id' => $tenant?->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/admin/{$tenant->getRouteKey()}/tiles/{$tile->id}/edit");

        $response->assertSuccessful();
        // Check that the form contains the background_blocks field
        $response->assertSee('background_blocks', false);
    }

    public function test_tile_can_be_updated_with_background_blocks(): void
    {
        $tile = Tile::create([
            'title' => ['de' => 'Test Tile', 'en' => 'Test Tile'],
            'background_blocks' => ['de' => [], 'en' => []],
            'tenant_id' => $this->tenant?->id,
        ]);

        $blocks = [
            'de' => [
                [
                    'type' => 'hero',
                    'data' => [
                        'title' => 'Hero Title',
                        'subtitle' => 'Hero Subtitle',
                    ],
                ],
            ],
            'en' => [],
        ];

        // Update tile directly (simulating form submission)
        $tile->update([
            'background_blocks' => $blocks,
        ]);

        $tile->refresh();
        $deBlocks = $tile->getTranslation('background_blocks', 'de');
        $this->assertNotNull($deBlocks);
        $this->assertCount(1, $deBlocks);
        $this->assertEquals('hero', $deBlocks[0]['type']);
    }

    public function test_tile_background_blocks_field_is_in_form_schema(): void
    {
        // Create a mock Livewire component
        $livewire = new class extends EditRecord
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
