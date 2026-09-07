<?php

namespace App\Models;

use App\Models\Concerns\AssignsSequentialPosition;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

class Tile extends Model
{
    use AssignsSequentialPosition;
    use BelongsToTenant;
    use HasFactory;
    use HasTranslations;

    protected function positionScopeColumn(): string
    {
        return 'tenant_id';
    }

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
        'hint',
        'slug',
        'meta_title',
        'meta_description',
        'background_blocks',
    ];

    protected $fillable = [
        'title',
        'description',
        'hint',
        'slug',
        'icon',
        'position',
        'time_granularity',
        'background_blocks',
        'is_public',
        'meta_title',
        'meta_description',
        'meta_image',
        'tenant_id',
    ];

    protected $casts = [
        'title' => 'array',
        'description' => 'array',
        'hint' => 'array',
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
     * @return BelongsToMany Relationship instance for the associated Category models.
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

    /**
     * Get the time periods for this tile, ordered by sort then period_key.
     */
    public function timePeriods()
    {
        return $this->hasMany(TimePeriod::class)->orderBy('sort')->orderBy('period_key');
    }

    /**
     * Get the has-many relationship for metric definitions belonging to this tile.
     *
     * @return HasMany A has-many relationship to MetricDefinition models.
     */
    public function metricDefinitions()
    {
        return $this->hasMany(MetricDefinition::class)->orderBy('sort_order');
    }

    /**
     * Get the tenant that owns the tile.
     *
     * @return BelongsTo The tenant that owns the tile.
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
