<?php

namespace App\Console\Commands;

use App\Models\BackgroundPage;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\FooterNavigation;
use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Navigation;
use App\Models\Page;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TileYear;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DashboardResetCommand extends Command
{
    protected $signature = 'dashboard:reset
                            {--force : Skip confirmation (for cron)}';

    protected $description = 'Reset demo tenants: Default cleaned, Regensburg re-seeded, Demo City emptied.';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will delete and re-seed demo tenant data. Continue?')) {
            $this->info('Aborted.');

            return Command::SUCCESS;
        }

        try {
            $this->fetchDashboardJson();
            $this->resetDefault();
            $this->resetRegensburg();
            $this->resetDemoCity();

            $this->info('Demo data reset completed successfully.');
            Log::info('dashboard:reset completed successfully');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Dashboard reset failed', ['exception' => $e]);
            $this->error('Reset failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    protected function fetchDashboardJson(): void
    {
        $url = config('seeding.dashboard_json_url');

        if (! $url) {
            $this->info('No dashboard_json_url configured, using existing local file.');

            return;
        }

        $this->info("Fetching dashboard.json from {$url}...");

        $response = Http::timeout(30)->get($url);

        if ($response->failed()) {
            throw new \RuntimeException("Failed to fetch dashboard.json from {$url} (HTTP {$response->status()})");
        }

        $storagePath = config('seeding.default_json_path');
        $directory = dirname($storagePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($storagePath, $response->body());
        $this->info("Saved dashboard.json to {$storagePath}");
    }

    protected function ensureTenantExists(string $slug, string $name): Tenant
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name]
        );

        // Only set domain for newly created tenants (no domain yet).
        // TenantSeeder is the canonical source for domain mappings.
        if (! $tenant->domain) {
            $baseDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
            $tenant->update(['domain' => "{$slug}.{$baseDomain}"]);
        }

        // Attach all existing users to the tenant
        $userIds = User::pluck('id');
        if ($userIds->isNotEmpty()) {
            $tenant->users()->syncWithoutDetaching($userIds);
        }

        return $tenant;
    }

    protected function resetRegensburg(): void
    {
        $tenant = $this->ensureTenantExists('stadt-regensburg', 'Stadt Regensburg');

        $this->info("Resetting Regensburg (tenant #{$tenant->id})...");

        DB::transaction(function () use ($tenant) {
            $this->deleteTenantContent($tenant);
        });

        $this->info('Re-seeding Regensburg...');

        Filament::setTenant($tenant, isQuiet: true);
        try {
            Artisan::call('dashboard:seed', [], $this->output);
            Artisan::call('pages:seed', [], $this->output);
            Artisan::call('tenancy:backfill', ['--default-tenant' => 'stadt-regensburg'], $this->output);
        } finally {
            Filament::setTenant(null, isQuiet: true);
        }

        $tileCount = Tile::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count();
        $this->info("Regensburg reset complete: {$tileCount} tiles seeded.");
    }

    protected function resetDemoCity(): void
    {
        $tenant = $this->ensureTenantExists('demo-city', 'Demo City');

        $this->info("Resetting Demo City (tenant #{$tenant->id})...");

        DB::transaction(function () use ($tenant) {
            $this->deleteTenantContent($tenant);
        });

        $this->seedMinimalRootPage($tenant);

        $this->info('Demo City reset to empty sandbox.');
    }

    protected function resetDefault(): void
    {
        $tenant = Tenant::where('slug', 'default')->first();

        if (! $tenant) {
            return;
        }

        $this->info("Resetting Default tenant (tenant #{$tenant->id})...");

        DB::transaction(function () use ($tenant) {
            $this->deleteTenantContent($tenant);
        });

        $this->seedMinimalRootPage($tenant);

        $this->info('Default tenant reset to clean state.');
    }

    protected function seedMinimalRootPage(Tenant $tenant): void
    {
        $exists = Page::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('layout', 'landingpage')
            ->exists();

        if ($exists) {
            return;
        }

        Page::create([
            'title' => ['de' => 'Dashboard', 'en' => 'Dashboard'],
            'slug' => ['de' => '/', 'en' => '/'],
            'blocks' => ['de' => [], 'en' => []],
            'layout' => 'landingpage',
            'is_public' => true,
            'tenant_id' => $tenant->id,
        ]);

        $this->info("Seeded root page for tenant '{$tenant->slug}'.");
    }

    protected function deleteTenantContent(Tenant $tenant): void
    {
        $tenantId = $tenant->id;

        // Delete in FK-safe order (children before parents)
        MetricValue::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->delete();

        if (Schema::hasTable('metrics')) {
            Metric::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->delete();
        }

        TileYear::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->delete();
        MetricDefinition::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->delete();
        BackgroundPage::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->delete();

        // Clear pivot table
        $tileIds = Tile::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->pluck('id');
        if ($tileIds->isNotEmpty()) {
            DB::table('category_tile')->whereIn('tile_id', $tileIds)->delete();
        }

        Tile::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->delete();
        Category::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->delete();
        CategoryGroup::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->delete();
        Navigation::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->delete();
        FooterNavigation::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->delete();
        Page::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->delete();
    }
}
