<?php

namespace Tests\Feature\Filament\Resources;

use App\Filament\Resources\CategoryGroupResource\Pages\CreateCategoryGroup;
use App\Filament\Resources\CategoryGroupResource\Pages\EditCategoryGroup;
use App\Filament\Resources\CategoryGroupResource\Pages\ListCategoryGroups;
use App\Filament\Resources\CategoryGroupResource\RelationManagers\CategoriesRelationManager;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryGroupResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $this->user = User::factory()->create();
        $this->user->tenants()->attach($this->tenant->id);

        $this->actingAs($this->user);
        Filament::setTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    // ========== List Page ==========

    public function test_list_page_renders(): void
    {
        Livewire::test(ListCategoryGroups::class)
            ->assertSuccessful();
    }

    public function test_list_page_shows_category_groups(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'test-group',
            'title' => ['de' => 'Testgruppe', 'en' => 'Test Group'],
        ]);

        Livewire::test(ListCategoryGroups::class)
            ->assertCanSeeTableRecords([$group]);
    }

    // ========== Create Page ==========

    public function test_create_page_renders(): void
    {
        Livewire::test(CreateCategoryGroup::class)
            ->assertSuccessful();
    }

    public function test_create_key_is_required(): void
    {
        Livewire::test(CreateCategoryGroup::class)
            ->fillForm([
                'key' => '',
                'title' => 'Test',
                'selection_type' => 'multi',
                'position' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['key' => 'required']);
    }

    public function test_create_key_must_match_regex(): void
    {
        Livewire::test(CreateCategoryGroup::class)
            ->fillForm([
                'key' => 'Invalid Key!',
                'title' => 'Test',
                'selection_type' => 'multi',
                'position' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['key']);
    }

    public function test_create_key_rejects_uppercase(): void
    {
        Livewire::test(CreateCategoryGroup::class)
            ->fillForm([
                'key' => 'UpperCase',
                'title' => 'Test',
                'selection_type' => 'multi',
                'position' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['key']);
    }

    public function test_create_key_rejects_starting_with_number(): void
    {
        Livewire::test(CreateCategoryGroup::class)
            ->fillForm([
                'key' => '1invalid',
                'title' => 'Test',
                'selection_type' => 'multi',
                'position' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['key']);
    }

    public function test_create_title_is_required(): void
    {
        Livewire::test(CreateCategoryGroup::class)
            ->fillForm([
                'key' => 'test-key',
                'title' => '',
                'selection_type' => 'multi',
                'position' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required']);
    }

    public function test_create_selection_type_is_required_when_filterable(): void
    {
        Livewire::test(CreateCategoryGroup::class)
            ->fillForm([
                'key' => 'test-key',
                'title' => 'Test',
                'is_filterable' => true,
                'selection_type' => null,
                'position' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['selection_type' => 'required']);
    }

    public function test_selection_type_hidden_when_not_filterable(): void
    {
        Livewire::test(CreateCategoryGroup::class)
            ->fillForm([
                'is_filterable' => false,
            ])
            ->assertFormFieldIsHidden('selection_type');
    }

    public function test_selection_type_visible_when_filterable(): void
    {
        Livewire::test(CreateCategoryGroup::class)
            ->fillForm([
                'is_filterable' => true,
            ])
            ->assertFormFieldIsVisible('selection_type');
    }

    public function test_create_form_accepts_valid_data(): void
    {
        Livewire::test(CreateCategoryGroup::class)
            ->fillForm([
                'key' => 'valid-key-123',
                'title' => 'Valid Group',
                'is_filterable' => true,
                'selection_type' => 'single',
                'is_color_source' => false,
                'is_active' => true,
                'position' => 5,
            ])
            ->assertFormSet([
                'key' => 'valid-key-123',
                'title' => 'Valid Group',
                'is_filterable' => true,
                'selection_type' => 'single',
                'is_color_source' => false,
                'is_active' => true,
                'position' => 5,
            ]);
    }

    public function test_create_category_group_completes_successfully(): void
    {
        Livewire::test(CreateCategoryGroup::class)
            ->fillForm([
                'key' => 'new-group',
                'title' => 'Neue Gruppe',
                'is_filterable' => false,
                'is_color_source' => false,
                'is_active' => true,
                'position' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('category_groups', [
            'key' => 'new-group',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_create_selection_type_defaults_to_multi(): void
    {
        Livewire::test(CreateCategoryGroup::class)
            ->assertFormSet([
                'selection_type' => 'multi',
            ]);
    }

    public function test_create_is_filterable_defaults_to_false(): void
    {
        Livewire::test(CreateCategoryGroup::class)
            ->assertFormSet([
                'is_filterable' => false,
            ]);
    }

    public function test_create_is_color_source_defaults_to_false(): void
    {
        Livewire::test(CreateCategoryGroup::class)
            ->assertFormSet([
                'is_color_source' => false,
            ]);
    }

    // ========== Edit Page ==========

    public function test_edit_page_renders(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create();

        Livewire::test(EditCategoryGroup::class, ['record' => $group->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_edit_page_loads_existing_data(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'loaded-group',
            'title' => ['de' => 'Geladene Gruppe', 'en' => 'Loaded Group'],
            'selection_type' => 'single',
            'is_filterable' => true,
            'is_color_source' => false,
            'is_active' => true,
            'position' => 10,
        ]);

        Livewire::test(EditCategoryGroup::class, ['record' => $group->getRouteKey()])
            ->assertFormSet([
                'key' => 'loaded-group',
                'title' => 'Geladene Gruppe',
                'selection_type' => 'single',
                'is_filterable' => true,
                'is_color_source' => false,
                'is_active' => true,
                'position' => 10,
            ]);
    }

    public function test_edit_key_field_is_disabled(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'immutable-key',
        ]);

        Livewire::test(EditCategoryGroup::class, ['record' => $group->getRouteKey()])
            ->assertFormFieldIsDisabled('key');
    }

    public function test_edit_page_saves_changes(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create([
            'key' => 'edit-test',
            'title' => ['de' => 'Alt', 'en' => 'Old'],
            'selection_type' => 'multi',
            'is_filterable' => false,
            'position' => 1,
        ]);

        Livewire::test(EditCategoryGroup::class, ['record' => $group->getRouteKey()])
            ->fillForm([
                'title' => 'Aktualisiert',
                'is_filterable' => true,
                'selection_type' => 'single',
                'position' => 50,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $group->refresh();
        $this->assertEquals('Aktualisiert', $group->getTranslation('title', 'de'));
        $this->assertEquals('single', $group->selection_type);
        $this->assertTrue($group->is_filterable);
        $this->assertEquals(50, $group->position);
    }

    public function test_edit_page_title_is_required(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create();

        Livewire::test(EditCategoryGroup::class, ['record' => $group->getRouteKey()])
            ->fillForm([
                'title' => '',
            ])
            ->call('save')
            ->assertHasFormErrors(['title' => 'required']);
    }

    // ========== Delete ==========

    public function test_can_delete_category_group_from_list(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create();

        Livewire::test(ListCategoryGroups::class)
            ->callTableAction('delete', $group);

        $this->assertDatabaseMissing('category_groups', ['id' => $group->id]);
    }

    // ========== Relation Manager ==========

    public function test_categories_relation_manager_renders(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create();

        Category::factory()->forGroup($group)->create([
            'slug' => ['de' => 'Kategorie A', 'en' => 'Category A'],
        ]);

        Livewire::test(CategoriesRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditCategoryGroup::class,
        ])
            ->assertSuccessful();
    }

    public function test_categories_relation_manager_shows_related_categories(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create();

        $category = Category::factory()->forGroup($group)->create([
            'slug' => ['de' => 'Verknuepfte Kategorie', 'en' => 'Related Category'],
        ]);

        Livewire::test(CategoriesRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditCategoryGroup::class,
        ])
            ->assertCanSeeTableRecords([$category]);
    }
}
