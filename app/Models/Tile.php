<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

class Tile extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasTranslations;

    protected static function booted(): void
    {
        static::saving(function (self $tile) {
            if (! Schema::hasColumn($tile->getTable(), 'slug')) {
                return;
            }

            $tile->slug = static::buildSlugs($tile);
        });
    }

    // List of JSON columns to translate:
    public array $translatable = [
        'title',
        'description',
        'slug',
        'meta_title',
        'meta_description',
    ];

    protected $fillable = [
        'title',
        'description',
        'slug',
        'icon',
        'position',
        'background_blocks',
        'is_public',
        'meta_title',
        'meta_description',
        'meta_image',
        'last_synced_at',
        'source_hash',
        'tenant_id',
    ];

    protected $casts = [
        'title' => 'array',
        'description' => 'array',
        'slug' => 'array',
        'background_blocks' => 'array',
        'is_public' => 'boolean',
        'meta_title' => 'array',
        'meta_description' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Defines the many-to-many relationship between the tile and Category models.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany Relationship instance for the associated Category models.
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * Get the handlungsfelder (categories) that belong to this tile.
     * Alias for categories() for consistency with new naming.
     */
    public function handlungsfelder()
    {
        return $this->belongsToMany(Category::class);
    }

    // Background blocks are now embedded directly in the tile model
    /**
     * Define a one-to-many relationship to TileYear models ordered by the `year` field ascending.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany Collection of TileYear models ordered by year.
     */
    public function tileYears()
    {
        return $this->hasMany(TileYear::class)->orderBy('year');
    }

    /**
     * Get the has-many relationship for metric definitions belonging to this tile.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany A has-many relationship to MetricDefinition models.
     */
    public function metricDefinitions()
    {
        return $this->hasMany(MetricDefinition::class);
    }

    /**
     * Get the tenant that owns the tile.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The tenant that owns the tile.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Build the frontend URL for this tile.
     *
     * @param  array<string, mixed>  $args
     */
    public function getUrl(array $args = []): string
    {
        $locale = $args['locale'] ?? app()->getLocale();
        $slug = $this->getTranslation('slug', $locale, false)
            ?: $this->getTranslation('slug', 'de', false);

        $slug = trim((string) $slug, '/');
        $suffix = $slug === '' ? '' : '/'.$slug;

        if ($locale === 'en') {
            return '/en/tiles'.$suffix;
        }

        return '/tiles'.$suffix;
    }

    public function getFrontendUrl(array $args = []): string
    {
        $path = $this->getUrl($args);
        $tenant = $this->tenant;

        if ($tenant && $tenant->domain) {
            $scheme = request()->getScheme();

            return "{$scheme}://{$tenant->domain}{$path}";
        }

        return $path;
    }

    protected static function buildSlugs(self $tile): array
    {
        $slugs = $tile->getTranslations('slug');

        if (! is_array($slugs)) {
            $slugs = [];
        }
        $locales = config('app.available_locales', ['de', 'en']);

        if (! is_array($locales) || $locales === []) {
            $locales = ['de', 'en'];
        }

        foreach ($locales as $locale) {
            $current = isset($slugs[$locale]) ? trim((string) $slugs[$locale]) : '';

            if ($current !== '') {
                continue;
            }

            $title = $tile->getTranslation('title', $locale, false)
                ?: $tile->getTranslation('title', 'de', false);

            if ($title) {
                $slugs[$locale] = Str::slug($title);
            }
        }

        return $slugs;
    }
}
