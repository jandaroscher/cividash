<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Handlungsdimension extends Model
{
    use HasTranslations;
    use BelongsToTenant;

    public array $translatable = [
        'title',
    ];

    protected $fillable = ['key', 'title', 'icon', 'position', 'color', 'tenant_id'];

    protected $casts = [
        'title' => 'array',
    ];

    /**
     * Get the table name for the model.
     */
    public function getTable(): string
    {
        return 'handlungsdimensionen';
    }

    /**
     * Get the handlungsfelder that belong to this dimension.
     */
    public function handlungsfelder()
    {
        return $this->belongsToMany(Category::class, 'handlungsdimension_handlungsfeld', 'handlungsdimension_id', 'handlungsfeld_id');
    }

    /**
     * Get the tiles that belong to this dimension.
     */
    public function tiles()
    {
        return $this->hasMany(Tile::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
