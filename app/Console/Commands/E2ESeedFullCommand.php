<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TileYear;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Seed comprehensive test data for E2E multi-tenant tests.
 *
 * Creates two tenants with tiles, categories, and API tokens
 * so Playwright can verify data isolation, API key lifecycle,
 * and admin panel access.
 */
class E2ESeedFullCommand extends Command
{
    protected $signature = 'e2e:seed-full
                            {--json : Output result as JSON for test consumption}
                            {--clean : Delete existing E2E test data before seeding}';

    protected $description = 'Seed full E2E test data: tenants, tiles, categories, tokens, and admin user.';

    public function handle(): int
    {
        return DB::transaction(function () {
            if ($this->option('clean')) {
                $this->cleanExistingData();
            }

            // Create two test tenants
            $tenantA = Tenant::updateOrCreate(
                ['slug' => 'e2e-tenant-a'],
                [
                    'name' => 'E2E Tenant A',
                    'domain' => 'a.open-source-dashboard.ddev.site',
                ]
            );

            $tenantB = Tenant::updateOrCreate(
                ['slug' => 'e2e-tenant-b'],
                [
                    'name' => 'E2E Tenant B',
                    'domain' => 'b.open-source-dashboard.ddev.site',
                ]
            );

            // Create category groups and categories for each tenant
            $this->createCategoriesForTenant($tenantA, 'A');
            $this->createCategoriesForTenant($tenantB, 'B');

            // Create tiles for each tenant
            $this->createTilesForTenant($tenantA, 'A');
            $this->createTilesForTenant($tenantB, 'B');

            // Create E2E admin user
            $user = User::firstOrCreate(
                ['email' => 'e2e-admin@example.com'],
                [
                    'name' => 'E2E Admin User',
                    'password' => bcrypt('e2e-admin-password'),
                ]
            );

            if (! $user->admin_api_enabled) {
                $user->forceFill(['admin_api_enabled' => true])->save();
            }

            // Associate user with both tenants
            $user->tenants()->syncWithoutDetaching([
                $tenantA->id,
                $tenantB->id,
            ]);

            // Revoke previous E2E full tokens
            $user->tokens()->where('name', 'like', 'e2e-full-%')->delete();

            // Create tokens scoped to each tenant
            $tokenA = $user->createToken('e2e-full-tenant-a', ['public-read', 'admin-api']);
            $tokenA->accessToken->tenant_id = $tenantA->id;
            $tokenA->accessToken->save();

            $tokenB = $user->createToken('e2e-full-tenant-b', ['public-read', 'admin-api']);
            $tokenB->accessToken->tenant_id = $tenantB->id;
            $tokenB->accessToken->save();

            // Create a lifecycle token (for revocation tests)
            $lifecycleToken = $user->createToken('e2e-full-lifecycle', ['public-read']);
            $lifecycleToken->accessToken->tenant_id = $tenantA->id;
            $lifecycleToken->accessToken->save();

            $result = [
                'success' => true,
                'tokens' => [
                    'tenantA' => $tokenA->plainTextToken,
                    'tenantB' => $tokenB->plainTextToken,
                    'lifecycle' => $lifecycleToken->plainTextToken,
                    'lifecycleId' => $lifecycleToken->accessToken->id,
                ],
                'tenants' => [
                    'tenantA' => [
                        'id' => $tenantA->id,
                        'slug' => $tenantA->slug,
                        'domain' => $tenantA->domain,
                    ],
                    'tenantB' => [
                        'id' => $tenantB->id,
                        'slug' => $tenantB->slug,
                        'domain' => $tenantB->domain,
                    ],
                ],
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'password' => 'e2e-admin-password',
                ],
            ];

            if ($this->option('json')) {
                $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->info('E2E full test data seeded successfully.');
                $this->newLine();
                $this->table(
                    ['Tenant', 'Slug', 'Domain', 'Tiles', 'Categories'],
                    [
                        ['Tenant A', $tenantA->slug, $tenantA->domain, Tile::where('tenant_id', $tenantA->id)->count(), Category::where('tenant_id', $tenantA->id)->count()],
                        ['Tenant B', $tenantB->slug, $tenantB->domain, Tile::where('tenant_id', $tenantB->id)->count(), Category::where('tenant_id', $tenantB->id)->count()],
                    ]
                );
                $this->newLine();
                $this->info('Tokens created:');
                $this->line("  Tenant A: {$tokenA->plainTextToken}");
                $this->line("  Tenant B: {$tokenB->plainTextToken}");
                $this->line("  Lifecycle: {$lifecycleToken->plainTextToken} (ID: {$lifecycleToken->accessToken->id})");
                $this->newLine();
                $this->info("Admin login: {$user->email} / e2e-admin-password");
            }

            return self::SUCCESS;
        });
    }

    protected function createCategoriesForTenant(Tenant $tenant, string $label): void
    {
        $group = CategoryGroup::updateOrCreate(
            ['key' => "e2e-group-{$tenant->slug}", 'tenant_id' => $tenant->id],
            [
                'title' => ['de' => "Gruppe {$label}", 'en' => "Group {$label}"],
                'position' => 1,
                'is_filterable' => true,
                'is_active' => true,
            ]
        );

        for ($i = 1; $i <= 3; $i++) {
            Category::updateOrCreate(
                ['key' => "e2e-cat-{$tenant->slug}-{$i}", 'tenant_id' => $tenant->id],
                [
                    'slug' => ['de' => "kategorie-{$label}-{$i}", 'en' => "category-{$label}-{$i}"],
                    'position' => $i,
                    'is_active' => true,
                    'category_group_id' => $group->id,
                ]
            );
        }
    }

    protected function createTilesForTenant(Tenant $tenant, string $label): void
    {
        $categories = Category::where('tenant_id', $tenant->id)->get();

        for ($i = 1; $i <= 3; $i++) {
            $tile = Tile::updateOrCreate(
                ['tenant_id' => $tenant->id, 'position' => $i],
                [
                    'title' => ['de' => "Kachel {$label}-{$i}", 'en' => "Tile {$label}-{$i}"],
                    'description' => ['de' => "Beschreibung {$label}-{$i}", 'en' => "Description {$label}-{$i}"],
                    'slug' => ['de' => "kachel-{$label}-{$i}", 'en' => "tile-{$label}-{$i}"],
                    'is_public' => true,
                ]
            );

            // Attach one category to each tile
            if ($categories->has($i - 1)) {
                $tile->categories()->syncWithoutDetaching([$categories[$i - 1]->id]);
            }

            // Create a tile year
            TileYear::updateOrCreate(
                ['tile_id' => $tile->id, 'year' => 2024, 'tenant_id' => $tenant->id],
            );
        }
    }

    protected function cleanExistingData(): void
    {
        $user = User::where('email', 'e2e-admin@example.com')->first();
        if ($user) {
            $user->tokens()->where('name', 'like', 'e2e-full-%')->delete();
        }

        // Clean up tiles and categories for E2E tenants
        $tenantIds = Tenant::whereIn('slug', ['e2e-tenant-a', 'e2e-tenant-b'])->pluck('id');
        if ($tenantIds->isNotEmpty()) {
            TileYear::whereIn('tenant_id', $tenantIds)->delete();
            Tile::whereIn('tenant_id', $tenantIds)->delete();
            Category::whereIn('tenant_id', $tenantIds)->delete();
            CategoryGroup::whereIn('tenant_id', $tenantIds)->delete();
            Tenant::whereIn('id', $tenantIds)->delete();
        }

        // Also remove any tenants that hold the domains we need (e.g. from tenant-resolution spec)
        Tenant::whereIn('domain', [
            'a.open-source-dashboard.ddev.site',
            'b.open-source-dashboard.ddev.site',
        ])->delete();
    }
}
