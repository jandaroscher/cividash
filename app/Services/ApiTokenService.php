<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenService
{
    /**
     * Allowed abilities for API tokens.
     * Deliberately not including '*' to enforce principle of least privilege.
     */
    public const ALLOWED_ABILITIES = ['admin-api', 'public-read'];

    /**
     * Retrieve all personal access tokens belonging to the given tenant.
     *
     * Excludes tokens with a null tenant_id and eager-loads the token owner; results are ordered by newest first.
     *
     * @param  Tenant  $tenant  The tenant whose tokens should be listed.
     * @return Collection<PersonalAccessToken> Collection of tokens ordered by newest first.
     */
    public function listForTenant(Tenant $tenant): Collection
    {
        return PersonalAccessToken::query()
            ->where('tenant_id', $tenant->id)
            ->with('tokenable') // Eager load owner for display
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Create a new API token for a user scoped to a tenant.
     *
     * Validates that at least one ability is provided, that all abilities are listed
     * in ALLOWED_ABILITIES, and that the user has admin_api_enabled when requesting
     * the `admin-api` ability.
     *
     * @param  User  $user  The user who will own the token.
     * @param  Tenant  $tenant  The tenant context to scope the token to.
     * @param  string  $name  Display name for the token.
     * @param  array  $abilities  Array of abilities for the token; must contain at least one entry and only values from ALLOWED_ABILITIES.
     * @return NewAccessToken The newly created access token (includes `plainTextToken`).
     *
     * @throws ValidationException When abilities are empty, contain invalid entries, or `admin-api` is requested but the user lacks admin_api_enabled.
     */
    public function createForTenant(User $user, Tenant $tenant, string $name, array $abilities): NewAccessToken
    {
        // Validate abilities are not empty
        if (empty($abilities)) {
            throw ValidationException::withMessages([
                'abilities' => ['At least one ability must be selected.'],
            ]);
        }

        // Validate abilities against allowlist
        $invalidAbilities = array_diff($abilities, self::ALLOWED_ABILITIES);
        if (! empty($invalidAbilities)) {
            throw ValidationException::withMessages([
                'abilities' => ['Invalid abilities: '.implode(', ', $invalidAbilities).'. Allowed: '.implode(', ', self::ALLOWED_ABILITIES)],
            ]);
        }

        // Check admin_api_enabled requirement for admin-api ability
        if (in_array('admin-api', $abilities) && ! $user->admin_api_enabled) {
            throw ValidationException::withMessages([
                'abilities' => ['The admin-api ability requires admin_api_enabled to be true on the user account.'],
            ]);
        }

        // Create the token
        $newToken = $user->createToken($name, $abilities);

        // Associate with tenant (critical for Admin API to work)
        $newToken->accessToken->tenant_id = $tenant->id;
        $newToken->accessToken->save();

        return $newToken;
    }

    /**
     * Revoke a personal access token scoped to the given tenant.
     *
     * @param  PersonalAccessToken  $token  The token to revoke.
     * @param  Tenant  $tenant  The tenant that must own the token.
     *
     * @throws \InvalidArgumentException If the token's tenant_id does not match the provided tenant's id.
     */
    public function revokeForTenant(PersonalAccessToken $token, Tenant $tenant): void
    {
        // Security: Ensure token belongs to the specified tenant
        if ($token->tenant_id !== $tenant->id) {
            throw new \InvalidArgumentException(
                'Cannot revoke token: Token does not belong to the specified tenant.'
            );
        }

        $token->delete();
    }

    /**
     * Get available abilities for a user.
     *
     * Returns the abilities that the user is allowed to select when creating tokens.
     * Users without admin_api_enabled cannot create admin-api tokens.
     *
     * @param  User  $user  The user to check abilities for
     * @return array Available abilities with labels
     */
    public function getAvailableAbilitiesForUser(User $user): array
    {
        $abilities = [
            'public-read' => 'Public Read (read-only access to public API endpoints)',
        ];

        if ($user->admin_api_enabled) {
            $abilities['admin-api'] = 'Admin API (full CRUD access to admin endpoints)';
        }

        return $abilities;
    }
}
