<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\FooterNavigation;
use App\Models\Media;
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
 *
 * Everything the suite creates carries MARKER in its title or file name, so --clean
 * removes exactly that and leaves other content alone.
 */
class E2ESeedMediaCommand extends Command
{
    public const MARKER = 'e2e-media';

    /** The backup lives in the settings table itself, so a database reset drops it too. */
    private const SETTINGS_BACKUP = ['tenant_id' => 0, 'group' => 'e2e', 'name' => 'media_settings_backup'];

    protected $signature = 'e2e:seed-media
                            {--legacy : Add content that references files by path}
                            {--clean : Remove what the media library tests created}';

    protected $description = 'Seed test data for the media library E2E tests (local and CI only)';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command only runs in the local and testing environments.');

            return self::FAILURE;
        }

        $default = Tenant::where('slug', 'default')->firstOrFail();
        $other = Tenant::where('slug', 'e2e-tenant-b')->firstOrFail();
        User::where('email', 'e2e-admin@example.com')->firstOrFail()->tenants()->syncWithoutDetaching([$default->id, $other->id]);

        if ($this->option('clean')) {
            $this->clean([$default->id, $other->id]);

            return self::SUCCESS;
        }

        $group = CategoryGroup::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $default->id, 'key' => self::MARKER],
            ['title' => ['de' => 'E2E Medien', 'en' => 'E2E media'], 'position' => 99, 'is_active' => true],
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
        $run = self::MARKER.'-'.Str::lower(Str::random(6));
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

        $this->backUpSettings([[$tenant->id, 'general', 'favicon'], [$tenant->id, 'branding', 'logo_url'], [0, 'branding', 'logo_url']]);
        $this->setting($tenant->id, 'general', 'favicon', $files['favicon']);
        // The dashboard's own logo would hide the inherited one
        DB::table('settings')->where(['tenant_id' => $tenant->id, 'group' => 'branding', 'name' => 'logo_url'])->delete();
        $this->setting(0, 'branding', 'logo_url', $files['logo']);

        return ['run' => $run, 'files' => $files, 'page' => $page->id, 'slugs' => ['de' => "alte-bilder-{$run}", 'en' => "old-images-{$run}"], 'tile' => $tile->id];
    }

    /** @param list<int> $tenants */
    private function clean(array $tenants): void
    {
        $like = '%'.self::MARKER.'%';
        $marked = fn ($query, string $column) => $query->withoutGlobalScopes()->whereIn('tenant_id', $tenants)->whereRaw("CAST({$column} AS TEXT) LIKE ?", [$like]);
        $media = $marked(Media::query(), 'title')->orWhere(fn ($q) => $q->whereIn('tenant_id', $tenants)->where('path', 'like', $like))->get();
        // A setting holds a path or a media id, as a string or a number
        $ours = function (mixed $value) use ($media): bool {
            $value = is_scalar($value) ? (string) $value : '';

            return str_contains($value, self::MARKER) || (ctype_digit($value) && $media->contains('id', (int) $value));
        };

        $this->restoreSettings();

        // Logos and favicons point at test files only if a test set them
        DB::table('settings')->whereIn('tenant_id', [0, ...$tenants])->whereIn('name', ['logo_url', 'favicon'])->get()
            ->filter(fn ($row) => $ours(json_decode($row->payload, true)))
            ->each(fn ($row) => (int) $row->tenant_id === 0
                ? $this->setting(0, $row->group, $row->name, null)
                : DB::table('settings')->where('id', $row->id)->delete());

        FooterNavigation::withoutGlobalScopes()->whereIn('tenant_id', $tenants)->get()->each(function (FooterNavigation $footer) {
            foreach ($footer->getTranslations('sponsors') as $locale => $sponsors) {
                $footer->setTranslation('sponsors', $locale, array_values(array_filter(
                    (array) $sponsors,
                    fn ($sponsor) => ! str_contains((string) ($sponsor['name'] ?? ''), self::MARKER),
                )));
            }
            $footer->save();
        });

        $marked(Page::query(), 'title')->get()->each->delete();
        $marked(Tile::query(), 'title')->get()->each->delete();
        $groups = CategoryGroup::withoutGlobalScopes()->whereIn('tenant_id', $tenants)->where('key', self::MARKER)->pluck('id');
        Category::withoutGlobalScopes()->whereIn('category_group_id', $groups)->get()->each->delete();
        CategoryGroup::withoutGlobalScopes()->whereKey($groups)->delete();
        // Deleting through the model also removes the stored file
        $media->each->delete();
        Storage::disk('public')->deleteDirectory('legacy-e2e');
    }

    /**
     * Keeps the settings rows the legacy content overwrites, so --clean can put them back.
     * A second seed keeps the first backup, which still holds the values from before the tests.
     *
     * @param  list<array{int, string, string}>  $keys  tenant id, group and name
     */
    private function backUpSettings(array $keys): void
    {
        if (DB::table('settings')->where(self::SETTINGS_BACKUP)->exists()) {
            return;
        }

        DB::table('settings')->insert([...self::SETTINGS_BACKUP, 'locked' => false, 'payload' => json_encode(array_map(function (array $key) {
            $where = array_combine(['tenant_id', 'group', 'name'], $key);
            $row = DB::table('settings')->where($where)->first(['payload', 'locked']);

            return ['where' => $where, 'row' => $row ? (array) $row : null];
        }, $keys))]);
    }

    /** Puts back the settings rows from before the seed, and removes those that did not exist. */
    private function restoreSettings(): void
    {
        $backup = DB::table('settings')->where(self::SETTINGS_BACKUP)->value('payload');
        if ($backup === null) {
            return;
        }

        foreach (json_decode($backup, true) as ['where' => $where, 'row' => $row]) {
            $row === null
                ? DB::table('settings')->where($where)->delete()
                : DB::table('settings')->updateOrInsert($where, [...$row, 'updated_at' => now()]);
        }
        DB::table('settings')->where(self::SETTINGS_BACKUP)->delete();
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
