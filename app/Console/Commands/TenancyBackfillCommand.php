<?php

namespace App\Console\Commands;

use App\Models\BackgroundPage;
use App\Models\Category;
use App\Models\FooterNavigation;
use App\Models\Handlungsdimension;
use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Navigation;
use App\Models\Page;
use App\Models\SDGZiel;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TileYear;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class TenancyBackfillCommand extends Command
{
    protected $signature = 'tenancy:backfill {--default-tenant=default : Slug of the default tenant to create or reuse}';

    protected $description = 'Create a default tenant and backfill existing records with tenant ownership.';

    /**
     * Create or reuse a default tenant (based on the `--default-tenant` option) and backfill existing records to associate them with that tenant inside a single database transaction.
     *
     * This command ensures a tenant with the requested slug exists, attaches that tenant to existing users (and sets users' default tenant when missing), and updates existing model records with a null `tenant_id` to point to the default tenant. The operation is atomic: changes are committed only if all backfill steps succeed.
     *
     * @return int Command exit status code: `SUCCESS` (0) on success.
     */
    public function handle(): int
    {
        $slug = $this->option('default-tenant') ?: 'default';

        DB::transaction(function () use ($slug) {
            $tenant = Tenant::firstOrCreate(
                ['slug' => $slug],
                ['name' => 'Default Tenant']
            );

            $this->backfillUsers($tenant);
            $this->backfillModels($tenant);
        });

        $this->info('Tenancy backfill completed.');

        return self::SUCCESS;
    }

    /**
     * Associate every existing user with the given tenant and set that tenant as the user's default when none is set.
     *
     * Existing tenant associations for users are preserved; only users without a default tenant will have their `default_tenant_id` set.
     *
     * @param  Tenant  $tenant  The tenant to attach to users and to use when populating missing default tenant assignments.
     */
    protected function backfillUsers(Tenant $tenant): void
    {
        User::query()->each(function (User $user) use ($tenant) {
            $user->tenants()->syncWithoutDetaching($tenant->id);

            if ($user->default_tenant_id === null) {
                $user->forceFill(['default_tenant_id' => $tenant->id])->save();
            }
        });
    }

    /**
     * Assign the given tenant's id to all existing records with a null `tenant_id` for a predefined set of models.
     *
     * For each model in the predefined list this updates records where `tenant_id` is null to use the provided tenant's id.
     * Tables that do not exist are skipped. The operation ignores the tenant global scope so records are found regardless
     * of any active tenant context.
     *
     * @param  Tenant  $tenant  The tenant whose `id` will be assigned to records with a null `tenant_id`.
     */
    protected function backfillModels(Tenant $tenant): void
    {
        $models = [
            Category::class,
            Tile::class,
            TileYear::class,
            SDGZiel::class,
            Handlungsdimension::class,
            Navigation::class,
            FooterNavigation::class,
            MetricDefinition::class,
            Metric::class,
            MetricValue::class,
            BackgroundPage::class,
            Page::class,
        ];

        foreach ($models as $modelClass) {
            $tableName = (new $modelClass)->getTable();

            // Skip if table doesn't exist (e.g., metrics table might not exist in all environments)
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            // Use withoutGlobalScope('tenant') to ensure all null tenant_id records are found
            // regardless of the active tenant context (e.g., when called via Artisan::call())
            $modelClass::withoutGlobalScope('tenant')
                ->whereNull('tenant_id')
                ->update(['tenant_id' => $tenant->id]);
        }
    }
}
