<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

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

    public function test_create_page_defaults_to_landingpage_when_none_exists(): void
    {
        $component = Livewire::test(CreatePage::class);

        $this->assertSame('landingpage', $component->get('data.layout'));
    }

    public function test_create_page_defaults_to_subpage_when_landingpage_exists(): void
    {
        Page::factory()->forTenant($this->tenant)->create(['layout' => 'landingpage']);

        $component = Livewire::test(CreatePage::class);

        $this->assertSame('subpage', $component->get('data.layout'));
    }

    // ========== Edit Page ==========

    public function test_edit_page_opens_in_the_locale_from_the_link(): void
    {
        app()->setLocale('de');
        $record = Page::factory()->forTenant($this->tenant)->create(['title' => ['de' => 'Deutscher Titel', 'en' => 'English title']]);

        Livewire::withQueryParams(['activeLocale' => 'en'])
            ->test(EditPage::class, ['record' => $record->getRouteKey()])
            ->assertSet('activeLocale', 'en')
            ->assertSchemaStateSet(['title' => 'English title']);
    }

    public function test_switching_locale_keeps_block_rich_text_of_the_other_locale_as_html(): void
    {
        app()->setLocale('de');
        $block = fn (string $heading, string $text): array => [[
            'type' => 'intro-text',
            'data' => ['heading' => $heading, 'subheading' => null, 'text' => $text, 'is_active' => true],
        ]];
        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Titel', 'en' => 'Title'],
            'blocks' => ['de' => $block('Hallo', '<p>Deutscher Text</p>'), 'en' => $block('Hello', '<p>English text</p>')],
        ]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->set('activeLocale', 'en')
            ->set('activeLocale', 'de')
            ->set('activeLocale', 'en')
            ->call('save')
            ->assertHasNoErrors();

        $page->refresh();
        $this->assertSame('<p>Deutscher Text</p>', $page->getTranslation('blocks', 'de')[0]['data']['text']);
        $this->assertSame('<p>English text</p>', $page->getTranslation('blocks', 'en')[0]['data']['text']);
        $this->assertTrue(array_is_list($page->getTranslation('blocks', 'de')));
    }

    public function test_german_only_page_with_a_slug_in_every_locale_saves_in_german(): void
    {
        // The homepage gets the slug "/" in every locale, but its other fields only in German.
        app()->setLocale('de');
        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Startseite'],
            'slug' => ['de' => '/', 'en' => '/'],
            'layout' => 'landingpage',
        ]);

        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['title' => 'Neue Startseite'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('de', $component->get('activeLocale'));
        $this->assertSame('Neue Startseite', $page->refresh()->getTranslation('title', 'de'));
    }

    public function test_an_untranslated_landing_page_locale_keeps_its_slug_when_switched_to(): void
    {
        app()->setLocale('de');
        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Startseite'],
            'slug' => ['de' => '/', 'en' => '/'],
            'layout' => 'landingpage',
        ]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->set('activeLocale', 'en')
            ->assertSchemaStateSet(['slug' => '/']);
    }

    public function test_a_locale_without_a_title_keeps_its_other_fields_when_switched_to(): void
    {
        app()->setLocale('de');
        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Seite'],
            'slug' => ['de' => 'seite', 'en' => 'page'],
            'meta_description' => ['de' => 'Beschreibung', 'en' => 'Description'],
        ]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->set('activeLocale', 'en')
            ->assertSchemaStateSet(['meta_description' => 'Description']);
    }

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

    public function test_edit_landingpage_saves_without_slug_validation_error(): void
    {
        $page = Page::factory()->forTenant($this->tenant)->create([
            'title' => ['de' => 'Startseite', 'en' => 'Homepage'],
            'slug' => ['de' => '/', 'en' => '/'],
            'layout' => 'landingpage',
        ]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm([
                'title' => 'Neuer Titel',
                'slug' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $page->refresh();
        $this->assertEquals('Neuer Titel', $page->getTranslation('title', 'de'));
        $this->assertEquals('/', $page->getTranslation('slug', 'de'));
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
