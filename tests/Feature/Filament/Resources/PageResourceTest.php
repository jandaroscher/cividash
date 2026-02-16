<?php

namespace Tests\Feature\Filament\Resources;

use App\Filament\Fabricator\Resources\PageResource\Pages\CreatePage;
use App\Filament\Fabricator\Resources\PageResource\Pages\EditPage;
use App\Filament\Fabricator\Resources\PageResource\Pages\ListPages;
use App\Models\Page;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PageResourceTest extends TestCase
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
        Livewire::test(ListPages::class)
            ->assertSuccessful();
    }

    public function test_list_page_shows_pages(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Testseite', 'en' => 'Test Page'],
        ]);

        Livewire::test(ListPages::class)
            ->assertCanSeeTableRecords([$page]);
    }

    // ========== Create Page ==========

    public function test_create_page_renders(): void
    {
        Livewire::test(CreatePage::class)
            ->assertSuccessful();
    }

    public function test_create_page_title_is_required(): void
    {
        Livewire::test(CreatePage::class)
            ->fillForm([
                'title' => '',
                'slug' => 'test',
                'layout' => 'default',
            ])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required']);
    }

    public function test_create_page_slug_is_required(): void
    {
        Livewire::test(CreatePage::class)
            ->fillForm([
                'title' => 'Test',
                'slug' => '',
                'layout' => 'default',
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'required']);
    }

    public function test_create_page_layout_is_required(): void
    {
        Livewire::test(CreatePage::class)
            ->fillForm([
                'title' => 'Test',
                'slug' => 'test',
                'layout' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['layout' => 'required']);
    }

    public function test_create_page_form_accepts_valid_data(): void
    {
        Livewire::test(CreatePage::class)
            ->fillForm([
                'title' => 'Neue Seite',
                'slug' => 'neue-seite',
                'layout' => 'default',
            ])
            ->assertFormSet([
                'title' => 'Neue Seite',
                'slug' => 'neue-seite',
                'layout' => 'default',
            ]);
    }

    public function test_create_page_layout_has_a_default(): void
    {
        $component = Livewire::test(CreatePage::class);
        $layout = $component->get('data.layout');

        $this->assertNotNull($layout, 'Layout should have a default value');
    }

    // ========== Edit Page ==========

    public function test_edit_page_renders(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->create();

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_edit_page_loads_existing_data(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Bestehende Seite', 'en' => 'Existing Page'],
            'slug' => ['de' => 'bestehende-seite', 'en' => 'existing-page'],
            'layout' => 'default',
        ]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertFormSet([
                'title' => 'Bestehende Seite',
                'slug' => 'bestehende-seite',
                'layout' => 'default',
            ]);
    }

    public function test_edit_page_saves_changes(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Alt', 'en' => 'Old'],
            'slug' => ['de' => 'alt', 'en' => 'old'],
        ]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm([
                'title' => 'Aktualisiert',
                'slug' => 'aktualisiert',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $page->refresh();
        $this->assertEquals('Aktualisiert', $page->getTranslation('title', 'de'));
        $this->assertEquals('aktualisiert', $page->getTranslation('slug', 'de'));
    }

    public function test_edit_page_title_is_required(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->create();

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm([
                'title' => '',
            ])
            ->call('save')
            ->assertHasFormErrors(['title' => 'required']);
    }

    public function test_edit_page_slug_is_required(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->create();

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm([
                'slug' => '',
            ])
            ->call('save')
            ->assertHasFormErrors(['slug' => 'required']);
    }

    // ========== Delete ==========

    public function test_can_delete_page_from_list(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->create();

        Livewire::test(ListPages::class)
            ->callTableAction('delete', $page);

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }
}
