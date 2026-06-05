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

    public function test_create_title_is_required(): void
    {
        Livewire::test(CreateCategoryGroup::class)
            ->fillForm([
                'title' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required']);
    }

    public function test_create_key_auto_generated_from_title(): void
    {
        Livewire::test(CreateCategoryGroup::class)
            ->fillForm([
                'title' => 'Neue Gruppe',
                'is_active' => true,
            ])
            ->assertFormSet([
                'key' => 'neue-gruppe',
            ]);
    }

    public function test_create_category_group_completes_successfully(): void
    {
        Livewire::test(CreateCategoryGroup::class)
            ->fillForm([
                'title' => 'Neue Gruppe',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('category_groups', [
            'key' => 'neue-gruppe',
            'tenant_id' => $this->tenant->id,
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
            'is_active' => true,
        ]);

        Livewire::test(EditCategoryGroup::class, ['record' => $group->getRouteKey()])
            ->assertFormSet([
                'key' => 'loaded-group',
                'title' => 'Geladene Gruppe',
                'is_active' => true,
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
        ]);

        Livewire::test(EditCategoryGroup::class, ['record' => $group->getRouteKey()])
            ->fillForm([
                'title' => 'Aktualisiert',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $group->refresh();
        $this->assertEquals('Aktualisiert', $group->getTranslation('title', 'de'));
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

    // ========== Auto-Position ==========

    public function test_new_category_group_gets_auto_incremented_position(): void
    {
        CategoryGroup::factory()->forTenant($this->tenant)->create(['position' => 3]);
        $otherTenant = Tenant::create(['name' => 'Other Tenant', 'slug' => 'other-tenant']);
        CategoryGroup::factory()->forTenant($otherTenant)->create(['position' => 99]);

        $group = CategoryGroup::factory()->forTenant($this->tenant)->create(['position' => null]);

        $this->assertEquals(4, $group->position);
    }

    public function test_category_group_with_explicit_position_keeps_it(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create(['position' => 10]);

        $this->assertEquals(10, $group->position);
    }

    public function test_category_group_with_explicit_position_zero_keeps_it(): void
    {
        $group = CategoryGroup::factory()->forTenant($this->tenant)->create(['position' => 0]);

        $this->assertEquals(0, $group->position);
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
