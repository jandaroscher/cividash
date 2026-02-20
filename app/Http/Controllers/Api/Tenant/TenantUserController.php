<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TenantUserController extends Controller
{
    public function __construct(
        protected RoleService $roleService
    ) {}

    /**
     * List users in a tenant.
     *
     * @group Tenant Users
     *
     * @authenticated
     *
     * @urlParam tenant string required The tenant slug. Example: test-tenant
     *
     * @queryParam role string Filter by role (Admin, Redakteur). Example: Admin
     * @queryParam page int Page number for pagination. Example: 1
     * @queryParam per_page int Items per page (max 100). Example: 15
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "first_name": "John",
     *       "last_name": "Doe",
     *       "email": "john@example.com",
     *       "role": "Admin",
     *       "joined_at": "2026-01-15T10:00:00.000000Z"
     *     }
     *   ],
     *   "meta": {
     *     "current_page": 1,
     *     "last_page": 1,
     *     "per_page": 15,
     *     "total": 1
     *   }
     * }
     */
    public function index(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorizeForTenant($request, $tenant);

        $perPage = min($request->integer('per_page', 15), 100);
        $roleFilter = $request->input('role');

        $query = $tenant->users()->withPivot('created_at');

        if ($roleFilter === 'Admin') {
            $query->where('is_admin', true);
        } elseif ($roleFilter === 'Redakteur') {
            $query->where('is_admin', false);
        }

        $users = $query->orderBy('last_name')->paginate($perPage);

        return response()->json([
            'data' => $users->map(function ($user) use ($tenant) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role' => $this->roleService->getUserRoleInTenant($user, $tenant),
                    'joined_at' => $user->pivot->created_at,
                ];
            }),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Update a user's role in a tenant.
     *
     * @group Tenant Users
     *
     * @authenticated
     *
     * @urlParam tenant string required The tenant slug. Example: test-tenant
     * @urlParam user int required The user ID. Example: 1
     *
     * @bodyParam role string required The new role (Redakteur). Example: Redakteur
     *
     * @response 200 {
     *   "message": "User role updated successfully.",
     *   "user": {
     *     "id": 1,
     *     "first_name": "John",
     *     "last_name": "Doe",
     *     "email": "john@example.com",
     *     "role": "Redakteur"
     *   }
     * }
     */
    public function update(Request $request, Tenant $tenant, User $user): JsonResponse
    {
        $this->authorizeForTenant($request, $tenant);

        // Ensure target user is in the tenant
        if (! $tenant->users()->where('users.id', $user->id)->exists()) {
            abort(404, __('tenant_users.user_not_in_tenant'));
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', RoleService::DEFAULT_ROLES)],
        ]);

        $targetRole = $validated['role'];

        $this->roleService->assignRoleInTenant($user, $targetRole, $tenant);

        return response()->json([
            'message' => __('tenant_users.role_updated'),
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'role' => $targetRole,
            ],
        ]);
    }

    /**
     * Remove a user from a tenant.
     *
     * @group Tenant Users
     *
     * @authenticated
     *
     * @urlParam tenant string required The tenant slug. Example: test-tenant
     * @urlParam user int required The user ID. Example: 1
     *
     * @response 200 {
     *   "message": "User removed from tenant successfully."
     * }
     * @response 422 {
     *   "message": "Cannot remove an admin user from a tenant.",
     *   "errors": {"user": ["Cannot remove an admin user from a tenant."]}
     * }
     */
    public function destroy(Request $request, Tenant $tenant, User $user): JsonResponse
    {
        $this->authorizeForTenant($request, $tenant);

        // Ensure target user is in the tenant
        if (! $tenant->users()->where('users.id', $user->id)->exists()) {
            abort(404, __('tenant_users.user_not_in_tenant'));
        }

        // Admins are managed globally, not per-tenant
        if ($user->is_admin) {
            throw ValidationException::withMessages([
                'user' => [__('tenant_users.cannot_remove_admin')],
            ]);
        }

        // Remove roles and detach from tenant
        $this->roleService->removeAllRolesInTenant($user, $tenant);
        $tenant->users()->detach($user->id);

        return response()->json([
            'message' => __('tenant_users.user_removed'),
        ]);
    }

    /**
     * Authorize the request for tenant actions (admin only).
     */
    protected function authorizeForTenant(Request $request, Tenant $tenant): void
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! $user->is_admin) {
            abort(403, __('tenant_users.insufficient_permissions'));
        }
    }
}
