<?php

namespace App\Services;

use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

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
            ->with('tokenable')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Create a new API token for a user scoped to a tenant.
     *
     * Validates that at least one ability is provided and that all abilities are listed
     * in ALLOWED_ABILITIES.
     *
     * @param  User  $user  The user who will own the token.
     * @param  Tenant  $tenant  The tenant context to scope the token to.
     * @param  string  $name  Display name for the token.
     * @param  array  $abilities  Array of abilities for the token; must contain at least one entry and only values from ALLOWED_ABILITIES.
     * @return NewAccessToken The newly created access token (includes `plainTextToken`).
     *
     * @throws ValidationException When abilities are empty or contain invalid entries.
     */
    public function createForTenant(User $user, Tenant $tenant, string $name, array $abilities): NewAccessToken
    {
        $this->validateAbilities($abilities);

        // Create the token
        $newToken = $user->createToken($name, $abilities);

        // Associate with tenant (critical for Admin API to work)
        $newToken->accessToken->tenant_id = $tenant->id;
        $newToken->accessToken->is_active = true;
        $newToken->accessToken->save();

        return $newToken;
    }

    /**
     * Update a personal access token scoped to the given tenant.
     *
     * @param  PersonalAccessToken  $token  The token to update.
     * @param  Tenant  $tenant  The tenant that must own the token.
     * @param  array  $data  The data to update (name, abilities, is_active).
     *
     * @throws \InvalidArgumentException If the token does not belong to the tenant.
     * @throws ValidationException When abilities are invalid.
     */
    public function updateForTenant(PersonalAccessToken $token, Tenant $tenant, array $data): void
    {
        if ($token->tenant_id !== $tenant->id) {
            throw new \InvalidArgumentException(
                'Cannot update token: Token does not belong to the specified tenant.'
            );
        }

        if (array_key_exists('abilities', $data)) {
            $this->validateAbilities((array) $data['abilities']);
            $data['abilities'] = (array) $data['abilities'];
        }

        $token->update($data);
    }

    /**
     * Toggle the is_active status of a token scoped to the given tenant.
     *
     * @param  PersonalAccessToken  $token  The token to toggle.
     * @param  Tenant  $tenant  The tenant that must own the token.
     *
     * @throws \InvalidArgumentException If the token does not belong to the tenant.
     */
    public function toggleActiveForTenant(PersonalAccessToken $token, Tenant $tenant): void
    {
        if ($token->tenant_id !== $tenant->id) {
            throw new \InvalidArgumentException(
                'Cannot toggle token: Token does not belong to the specified tenant.'
            );
        }

        $token->update(['is_active' => ! $token->is_active]);
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
        if ($token->tenant_id !== $tenant->id) {
            throw new \InvalidArgumentException(
                'Cannot revoke token: Token does not belong to the specified tenant.'
            );
        }

        $token->delete();
    }

    /**
     * Get available abilities for token creation.
     *
     * @return array Available abilities with labels
     */
    public function getAvailableAbilities(): array
    {
        return [
            'public-read' => 'Public Read (read-only access to public API endpoints)',
            'admin-api' => 'Admin API (full CRUD access to admin endpoints)',
        ];
    }

    /**
     * Validate the given abilities array against the allowlist.
     *
     * @throws ValidationException When abilities are empty or contain invalid entries.
     */
    protected function validateAbilities(array $abilities): void
    {
        if (empty($abilities)) {
            throw ValidationException::withMessages([
                'abilities' => [__('filament.pages.manage_api_keys.validation.abilities_required')],
            ]);
        }

        $invalidAbilities = array_diff($abilities, self::ALLOWED_ABILITIES);
        if (! empty($invalidAbilities)) {
            throw ValidationException::withMessages([
                'abilities' => [__('filament.pages.manage_api_keys.validation.abilities_invalid', [
                    'invalid' => implode(', ', $invalidAbilities),
                    'allowed' => implode(', ', self::ALLOWED_ABILITIES),
                ])],
            ]);
        }
    }
}
