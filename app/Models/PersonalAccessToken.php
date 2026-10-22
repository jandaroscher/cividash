<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_active' => 'boolean',
        ]);
    }

    /**
     * Find the token instance matching the given token.
     *
     * Returns null for inactive and expired tokens so they fail authentication
     * automatically, also where the token is looked up outside the Sanctum guard
     * (tenant resolution).
     */
    public static function findToken($token): ?SanctumPersonalAccessToken
    {
        $model = parent::findToken($token);

        if ($model && (! $model->is_active || $model->expires_at?->isPast())) {
            return null;
        }

        return $model;
    }
}
