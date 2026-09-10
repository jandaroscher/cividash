<?php

namespace Tests\Feature\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected CategoryGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $this->user = User::factory()->create();
        $this->user->tenants()->attach($this->tenant->id);

        $this->actingAs($this->user);
        Filament::setTenant($this->tenant);

        $this->group = CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'test-group',
            'title' => ['de' => 'Testgruppe', 'en' => 'Test Group'],
        ]);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    // ========== List Page ==========

    public function test_list_page_renders(): void
    {
        Livewire::test(ListCategories::class)
            ->assertSuccessful();
    }

    public function test_list_page_shows_categories(): void
    {
        $category = Category::factory()->forGroup($this->group)->create([
            'slug' => ['de' => 'Umwelt', 'en' => 'Environment'],
        ]);

        Livewire::test(ListCategories::class)
            ->assertCanSeeTableRecords([$category]);
    }

    // ========== Create Page ==========

    public function test_create_page_renders(): void
    {
        Livewire::test(CreateCategory::class)
            ->assertSuccessful();
    }

    public function test_create_category_slug_is_required(): void
    {
        Livewire::test(CreateCategory::class)
            ->fillForm([
                'slug' => '',
                'category_group_id' => $this->group->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'required']);
    }

    public function test_create_category_group_is_required(): void
    {
        Livewire::test(CreateCategory::class)
            ->fillForm([
                'slug' => 'Test',
                'category_group_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['category_group_id' => 'required']);
    }

    public function test_create_category_form_accepts_valid_data(): void
    {
        Livewire::test(CreateCategory::class)
            ->fillForm([
                'slug' => 'Neue Kategorie',
                'category_group_id' => $this->group->id,
                'is_active' => true,
            ])
            ->assertFormSet([
                'slug' => 'Neue Kategorie',
                'category_group_id' => $this->group->id,
                'is_active' => true,
            ]);
    }

    public function test_create_category_is_active_defaults_to_true(): void
    {
        Livewire::test(CreateCategory::class)
            ->assertFormSet([
                'is_active' => true,
            ]);
    }

    // ========== Edit Page ==========

    public function test_edit_page_renders(): void
    {
        $category = Category::factory()->forGroup($this->group)->create();

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_edit_key_field_is_disabled(): void
    {
        $category = Category::factory()->forGroup($this->group)->create([
            'key' => 'immutable-key',
        ]);

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->assertFormFieldIsDisabled('key');
    }

    public function test_edit_page_loads_existing_data(): void
    {
        $category = Category::factory()->forGroup($this->group)->create([
            'slug' => ['de' => 'Mobilitat', 'en' => 'Mobility'],
            'is_active' => true,
        ]);

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->assertFormSet([
                'slug' => 'Mobilitat',
                'category_group_id' => $this->group->id,
                'is_active' => true,
            ]);
    }

    public function test_edit_page_saves_changes(): void
    {
        $category = Category::factory()->forGroup($this->group)->create([
            'slug' => ['de' => 'Alt', 'en' => 'Old'],
        ]);

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm([
                'slug' => 'Aktualisiert',
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $category->refresh();
        $this->assertEquals('Aktualisiert', $category->getTranslation('slug', 'de'));
        $this->assertFalse($category->is_active);
    }

    public function test_edit_page_slug_is_required(): void
    {
        $category = Category::factory()->forGroup($this->group)->create();

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm([
                'slug' => '',
            ])
            ->call('save')
            ->assertHasFormErrors(['slug' => 'required']);
    }

    // ========== Auto-Position ==========

    public function test_new_category_gets_auto_incremented_position(): void
    {
        Category::factory()->forGroup($this->group)->create(['position' => 5]);
        $otherGroup = CategoryGroup::factory()->forTenant($this->tenant)->create();
        Category::factory()->forGroup($otherGroup)->create(['position' => 99]);

        $category = Category::factory()->forGroup($this->group)->create(['position' => null]);

        $this->assertEquals(6, $category->position);
    }

    public function test_category_with_explicit_position_keeps_it(): void
    {
        $category = Category::factory()->forGroup($this->group)->create(['position' => 10]);

        $this->assertEquals(10, $category->position);
    }

    public function test_category_with_explicit_position_zero_keeps_it(): void
    {
        $category = Category::factory()->forGroup($this->group)->create(['position' => 0]);

        $this->assertEquals(0, $category->position);
    }

    // ========== Delete ==========

    public function test_can_delete_category_from_list(): void
    {
        $category = Category::factory()->forGroup($this->group)->create();

        Livewire::test(ListCategories::class)
            ->callTableAction('delete', $category);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    // ========== SVG icon sanitizing ==========

    public function test_uploaded_malicious_svg_icon_is_sanitized_on_disk(): void
    {
        Storage::fake('public');

        $maliciousSvg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)">'
            .'<script>alert(document.cookie)</script>'
            .'<circle r="1"/>'
            .'</svg>';

        $file = UploadedFile::fake()->createWithContent('malicious.svg', $maliciousSvg);

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'slug' => 'Malicious Icon Category',
                'category_group_id' => $this->group->id,
                'icon' => [$file],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::query()->latest('id')->firstOrFail();

        $decoded = json_decode($category->icon, true);
        $this->assertIsArray($decoded);
        $path = $decoded[app()->getLocale()] ?? $decoded['de'] ?? reset($decoded);

        $this->assertNotNull($path);
        $stored = Storage::disk('public')->get($path);

        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onload', $stored);
        $this->assertStringNotContainsString('alert', $stored);
        $this->assertStringContainsString('<circle', $stored);
    }
}
