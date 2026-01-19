<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Seed deterministic test data for E2E tenant resolution tests.
 *
 * Creates tenants with specific domains for Playwright API tests:
 * - Default tenant (slug=default)
 * - Tenant A (domain=a.open-source-dashboard.ddev.site)
 * - Tenant B (domain=b.open-source-dashboard.ddev.site)
 * - User with Sanctum token scoped to tenant A
 */
class E2ESeedTenantResolutionCommand extends Command
{
    protected $signature = 'e2e:seed-tenant-resolution
                            {--json : Output result as JSON for test consumption}
                            {--clean : Delete existing E2E test tenants before seeding}';

    protected $description = 'Seed deterministic tenants and tokens for E2E tenant resolution tests.';

    /**
     * Seed deterministic E2E tenants, a test user, and tenant-scoped API tokens used by tenant resolution tests.
     *
     * Creates or updates a default tenant and two test tenants with fixed slugs/domains, ensures a dedicated
     * E2E test user exists with admin API enabled, associates that user with the tenants, revokes prior E2E
     * tokens, and issues two new tokens explicitly bound to Tenant A and Tenant B. Supports a `--clean` option
     * to remove existing E2E tokens before seeding and a `--json` option to emit the result as JSON; otherwise
     * the command prints a summary table and the plain tokens.
     *
     * @return int Command exit status code (`SUCCESS` on success).
     */
    public function handle(): int
    {
        return DB::transaction(function () {
            if ($this->option('clean')) {
                $this->cleanExistingData();
            }

            // Create default tenant
            $defaultTenant = Tenant::firstOrCreate(
                ['slug' => 'default'],
                ['name' => 'Default Tenant']
            );

            // Create tenant A with domain
            // ddev additional_hostnames: a.open-source-dashboard -> a.open-source-dashboard.ddev.site
            $tenantA = Tenant::updateOrCreate(
                ['slug' => 'tenant-a'],
                [
                    'name' => 'Tenant A (E2E)',
                    'domain' => 'a.open-source-dashboard.ddev.site',
                ]
            );

            // Create tenant B with domain
            $tenantB = Tenant::updateOrCreate(
                ['slug' => 'tenant-b'],
                [
                    'name' => 'Tenant B (E2E)',
                    'domain' => 'b.open-source-dashboard.ddev.site',
                ]
            );

            // Create E2E test user
            $user = User::firstOrCreate(
                ['email' => 'e2e-test@example.com'],
                [
                    'name' => 'E2E Test User',
                    'password' => bcrypt('e2e-test-password'),
                ]
            );

            // Ensure user has admin_api_enabled
            if (! $user->admin_api_enabled) {
                $user->forceFill(['admin_api_enabled' => true])->save();
            }

            // Associate user with all tenants
            $user->tenants()->syncWithoutDetaching([
                $defaultTenant->id,
                $tenantA->id,
                $tenantB->id,
            ]);

            // Create token for tenant A (for precedence tests)
            $tokenA = $user->createToken('e2e-tenant-a-token', ['public-read', 'admin-api']);
            $tokenA->accessToken->tenant_id = $tenantA->id;
            $tokenA->accessToken->save();

            // Create token for tenant B (optional, for additional tests)
            $tokenB = $user->createToken('e2e-tenant-b-token', ['public-read']);
            $tokenB->accessToken->tenant_id = $tenantB->id;
            $tokenB->accessToken->save();

            $result = [
                'success' => true,
                'tokens' => [
                    'tenantA' => $tokenA->plainTextToken,
                    'tenantB' => $tokenB->plainTextToken,
                ],
                'tenants' => [
                    'default' => [
                        'id' => $defaultTenant->id,
                        'slug' => $defaultTenant->slug,
                        'domain' => $defaultTenant->domain,
                    ],
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
                ],
            ];

            if ($this->option('json')) {
                $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->info('E2E tenant resolution test data seeded successfully.');
                $this->newLine();
                $this->table(
                    ['Tenant', 'Slug', 'Domain'],
                    [
                        ['Default', $defaultTenant->slug, $defaultTenant->domain ?? '(none)'],
                        ['Tenant A', $tenantA->slug, $tenantA->domain],
                        ['Tenant B', $tenantB->slug, $tenantB->domain],
                    ]
                );
                $this->newLine();
                $this->info('Tokens created:');
                $this->line("  Tenant A: {$tokenA->plainTextToken}");
                $this->line("  Tenant B: {$tokenB->plainTextToken}");
            }

            return self::SUCCESS;
        });
    }

    /**
     * Remove existing E2E test API tokens for the dedicated E2E test user.
     *
     * Deletes any personal access tokens whose name begins with "e2e-" for the user with email
     * `e2e-test@example.com`. Does not remove tenant records. Emits an informational message when complete.
     */
    protected function cleanExistingData(): void
    {
        // Delete E2E user tokens
        $user = User::where('email', 'e2e-test@example.com')->first();
        if ($user) {
            $user->tokens()->where('name', 'like', 'e2e-%')->delete();
        }

        // Note: We don't delete tenants as they might have associated data
        // Instead, we update them with updateOrCreate
        $this->info('Cleaned existing E2E tokens.');
    }
}