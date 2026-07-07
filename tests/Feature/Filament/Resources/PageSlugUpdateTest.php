<?php

namespace Tests\Feature\Filament\Resources;

use App\Filament\Fabricator\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage: a page's slug must resolve reliably on the
 * FIRST save, both in cache/URL invalidation and in preventing silent slug
 * collisions between sibling pages (which manifest to users as "my slug
 * change didn't take effect" because the frontend then resolves the URL to
 * whichever sibling page happens to come first in the pages list).
 */
class PageSlugUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_change_is_reflected_immediately_after_first_save_raw_model(): void
    {
        $tenant = Tenant::where('slug', 'default')->first();

        $page = Page::factory()->forTenant($tenant)->create([
            'title' => ['de' => 'Testseite', 'en' => 'Test page'],
            'slug' => ['de' => 'testseite', 'en' => 'test-page'],
            'layout' => 'default',
            'is_public' => true,
        ]);

        // Warm the caches (list + getUrl), simulating a frontend visit before the edit.
        $resp1 = $this->getJson('/api/content/pages?locale=de');
        $resp1->assertOk();
        $this->assertTrue(collect($resp1->json('data'))->contains('slug', 'testseite'));

        $url1 = $page->getUrl(['locale' => 'de']);
        $this->assertEquals('/testseite', $url1);

        $page->setTranslation('slug', 'de', 'neuer-slug');
        $page->save();

        $listResp = $this->getJson('/api/content/pages?locale=de');
        $listResp->assertOk();
        $slugs = collect($listResp->json('data'))->pluck('slug');
        $this->assertTrue($slugs->contains('neuer-slug'), 'List cache still shows old slug after 1st save: '.$slugs->implode(','));
        $this->assertFalse($slugs->contains('testseite'), 'List cache still contains stale old slug after 1st save');

        $url2 = $page->getUrl(['locale' => 'de']);
        $this->assertEquals('/neuer-slug', $url2, 'getUrl() cache still shows old URL after 1st save');
    }

    public function test_slug_change_via_filament_admin_form_is_reflected_immediately(): void
    {
        $tenant = Tenant::create(['name' => 'Repro Tenant', 'slug' => 'repro-tenant']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id);
        $this->actingAs($user);
        Filament::setTenant($tenant);

        $page = Page::factory()->forTenant($tenant)->create([
            'title' => ['de' => 'Testseite', 'en' => 'Test page'],
            'slug' => ['de' => 'testseite', 'en' => 'test-page'],
            'layout' => 'default',
            'is_public' => true,
        ]);

        $resp1 = $this->withHeader('X-Tenant', $tenant->slug)
            ->getJson('/api/content/pages?locale=de');
        $resp1->assertOk();

        $url1 = $page->getUrl(['locale' => 'de']);
        $this->assertEquals('/testseite', $url1);

        // The preceding HTTP-level API call dispatches through the full kernel and
        // can reset Filament's tenant state; re-assert it before driving the form.
        Filament::setTenant($tenant);

        $capturedWasChanged = null;
        Page::updated(function (Page $p) use (&$capturedWasChanged) {
            $capturedWasChanged = $p->wasChanged('slug');
        });

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm([
                'slug' => 'neuer-slug',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($capturedWasChanged, 'wasChanged(slug) was false on the real Filament save pipeline even though the slug value changed');

        $page->refresh();
        $this->assertEquals('neuer-slug', $page->getTranslation('slug', 'de'), 'DB was not updated on first save');

        $url2 = $page->getUrl(['locale' => 'de']);
        $this->assertEquals('/neuer-slug', $url2, 'getUrl() cache still stale after 1st Filament save');

        $tenantKey = (string) $tenant->id;
        $this->assertNull(Cache::get("content_page:{$tenantKey}:de:{$page->id}"), 'content_page cache not flushed after 1st Filament save');
        $this->assertNull(Cache::get("content_pages_list:{$tenantKey}:de"), 'content_pages_list cache not flushed after 1st Filament save');
    }

    public function test_duplicate_slug_under_same_parent_is_rejected_via_admin_form(): void
    {
        $tenant = Tenant::create(['name' => 'Unique Slug Tenant', 'slug' => 'unique-slug-tenant']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id);
        $this->actingAs($user);
        Filament::setTenant($tenant);

        Page::factory()->forTenant($tenant)->create([
            'title' => ['de' => 'Kontakt', 'en' => 'Contact'],
            'slug' => ['de' => 'kontakt', 'en' => 'contact'],
            'parent_id' => null,
        ]);

        $editedPage = Page::factory()->forTenant($tenant)->create([
            'title' => ['de' => 'Testseite', 'en' => 'Test page'],
            'slug' => ['de' => 'testseite', 'en' => 'test-page'],
            'parent_id' => null,
        ]);

        // Attempting to rename the second page's slug to one already used by a
        // sibling (same parent_id) must be rejected with a validation error,
        // not silently accepted, since the frontend resolves pages by slug via
        // a first-match lookup and a collision would make it look like the
        // change was never applied.
        Livewire::test(EditPage::class, ['record' => $editedPage->getRouteKey()])
            ->fillForm([
                'slug' => 'kontakt',
            ])
            ->call('save')
            ->assertHasFormErrors(['slug']);

        $editedPage->refresh();
        $this->assertEquals('testseite', $editedPage->getTranslation('slug', 'de'), 'Slug must not change when the duplicate is rejected');
    }

    public function test_slug_can_be_changed_to_a_genuinely_free_value(): void
    {
        $tenant = Tenant::create(['name' => 'Free Slug Tenant', 'slug' => 'free-slug-tenant']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id);
        $this->actingAs($user);
        Filament::setTenant($tenant);

        $page = Page::factory()->forTenant($tenant)->create([
            'title' => ['de' => 'Testseite', 'en' => 'Test page'],
            'slug' => ['de' => 'testseite', 'en' => 'test-page'],
            'parent_id' => null,
        ]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm([
                'slug' => 'neuer-slug',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals('neuer-slug', $page->fresh()->getTranslation('slug', 'de'));
    }

    public function test_same_slug_is_allowed_under_different_parents(): void
    {
        $tenant = Tenant::create(['name' => 'Nested Slug Tenant', 'slug' => 'nested-slug-tenant']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id);
        $this->actingAs($user);
        Filament::setTenant($tenant);

        $parentA = Page::factory()->forTenant($tenant)->create([
            'slug' => ['de' => 'bereich-a', 'en' => 'section-a'],
            'parent_id' => null,
        ]);

        $parentB = Page::factory()->forTenant($tenant)->create([
            'slug' => ['de' => 'bereich-b', 'en' => 'section-b'],
            'parent_id' => null,
        ]);

        Page::factory()->forTenant($tenant)->create([
            'slug' => ['de' => 'kontakt', 'en' => 'contact'],
            'parent_id' => $parentA->id,
        ]);

        $childB = Page::factory()->forTenant($tenant)->create([
            'slug' => ['de' => 'anderer-slug', 'en' => 'other-slug'],
            'parent_id' => $parentB->id,
        ]);

        // Same slug ("kontakt") under a DIFFERENT parent must be allowed.
        Livewire::test(EditPage::class, ['record' => $childB->getRouteKey()])
            ->fillForm([
                'slug' => 'kontakt',
                'parent_id' => $parentB->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals('kontakt', $childB->fresh()->getTranslation('slug', 'de'));
    }
}
