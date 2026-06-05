<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRun extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'mode',
        'format',
        'filename',
        'byte_size',
        'status',
        'diff_summary',
        'error_count',
        'warning_count',
        'duration_ms',
    ];

    protected $casts = [
        'diff_summary' => 'array',
        'byte_size' => 'integer',
        'error_count' => 'integer',
        'warning_count' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
