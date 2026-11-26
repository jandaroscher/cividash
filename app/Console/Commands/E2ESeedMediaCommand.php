<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Console\Commands;

use App\Models\CategoryGroup;
use App\Models\Page;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Test data for the media library E2E suite. Run after e2e:seed-full.
 *
 * With --legacy it also stores content the way releases before the media library
 * did: file paths in blocks, tiles and settings, including a logo on the global
 * defaults that every dashboard inherits.
 */
class E2ESeedMediaCommand extends Command
{
    protected $signature = 'e2e:seed-media
                            {--legacy : Add content that references files by path}
                            {--reset-branding : Remove the logos and favicons set by these tests}';

    protected $description = 'Seed test data for the media library E2E tests (local and CI only)';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('This command cannot run in production.');

            return self::FAILURE;
        }

        if ($this->option('reset-branding')) {
            $default = Tenant::where('slug', 'default')->value('id');
            DB::table('settings')->where('tenant_id', $default)->whereIn('name', ['logo_url', 'favicon'])->delete();
            $this->setting(0, 'branding', 'logo_url', null);

            return self::SUCCESS;
        }

        $default = Tenant::where('slug', 'default')->firstOrFail();
        $other = Tenant::where('slug', 'e2e-tenant-b')->firstOrFail();
        User::where('email', 'e2e-admin@example.com')->firstOrFail()->tenants()->syncWithoutDetaching([$default->id, $other->id]);

        $group = CategoryGroup::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $default->id, 'key' => 'e2e-media'],
            ['title' => ['de' => 'Handlungsfelder', 'en' => 'Fields of action'], 'position' => 99, 'is_active' => true],
        );

        $result = ['defaultTenant' => $default->id, 'otherTenant' => $other->id, 'categoryGroup' => $group->key];

        if ($this->option('legacy')) {
            $result['legacy'] = $this->seedLegacy($default);
        }

        $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function seedLegacy(Tenant $tenant): array
    {
        $run = Str::lower(Str::random(6));
        $dir = "legacy-e2e/{$run}";
        $files = [];
        foreach (['harbour' => 'png', 'square' => 'png', 'water' => 'png', 'seo' => 'png', 'favicon' => 'png', 'logo' => 'png'] as $name => $ext) {
            $files[$name] = "{$dir}/{$name}-{$run}.{$ext}";
            Storage::disk('public')->put($files[$name], $this->png());
        }

        $blocks = fn (string $suffix) => [
            ['type' => 'text-image', 'data' => [
                'heading' => $suffix === 'DE' ? 'Unser Hafen' : 'Our harbour',
                'text' => '<p>'.($suffix === 'DE' ? 'Ein Blick auf den Hafen.' : 'A view of the harbour.').'</p>',
                'image' => $files['harbour'],
                'image_alt' => $suffix === 'DE' ? 'Boote im Hafen am Morgen' : 'Boats in the harbour in the morning',
                'image_copyright' => '© Stadtarchiv',
                'image_position' => 'right',
                'is_active' => true,
            ]],
            ['type' => 'slider', 'data' => [
                'heading' => $suffix === 'DE' ? 'Eindrücke' : 'Impressions',
                'is_active' => true,
                'items' => [
                    ['title' => $suffix === 'DE' ? 'Marktplatz' : 'Market square', 'image' => $files['square'], 'image_alt' => $suffix === 'DE' ? 'Marktplatz mit Brunnen' : 'Market square with fountain', 'is_active' => true],
                ],
            ]],
        ];

        $page = Page::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'title' => ['de' => "Alte Bilder {$run}", 'en' => "Old images {$run}"],
            'slug' => ['de' => "alte-bilder-{$run}", 'en' => "old-images-{$run}"],
            'layout' => 'subpage',
            'is_public' => true,
            'blocks' => ['de' => $blocks('DE'), 'en' => $blocks('EN')],
        ]);

        $tile = Tile::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'title' => ['de' => "Wasserqualität {$run}", 'en' => "Water quality {$run}"],
            'slug' => ['de' => "wasser-{$run}", 'en' => "water-{$run}"],
            'icon' => $files['water'],
            'meta_image' => $files['seo'],
            'is_public' => true,
        ]);

        $this->setting($tenant->id, 'general', 'favicon', $files['favicon']);
        $this->setting(0, 'branding', 'logo_url', $files['logo']);

        return ['run' => $run, 'files' => $files, 'page' => $page->id, 'slugs' => ['de' => "alte-bilder-{$run}", 'en' => "old-images-{$run}"], 'tile' => $tile->id];
    }

    private function setting(int $tenantId, string $group, string $name, ?string $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['tenant_id' => $tenantId, 'group' => $group, 'name' => $name],
            ['payload' => json_encode($value), 'locked' => false, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /** A small PNG with random pixels, so every run adds new files. */
    private function png(): string
    {
        $image = imagecreatetruecolor(320, 200);
        imagefill($image, 0, 0, imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        ob_start();
        imagepng($image);

        return ob_get_clean();
    }
}
