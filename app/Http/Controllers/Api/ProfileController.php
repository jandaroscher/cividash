<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user's profile.
     *
     * @group Profile
     *
     * @authenticated
     *
     * @response 200 {
     *   "id": 1,
     *   "first_name": "John",
     *   "last_name": "Doe",
     *   "email": "john@example.com",
     *   "locale": "de",
     *   "email_verified_at": "2026-01-15T10:00:00.000000Z",
     *   "tenants": [
     *     {"id": 1, "name": "Test Tenant", "slug": "test-tenant"}
     *   ],
     *   "default_tenant_id": 1
     * }
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('tenants:id,name,slug');

        return response()->json([
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'locale' => $user->locale,
            'email_verified_at' => $user->email_verified_at,
            'tenants' => $user->tenants->map(fn ($tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ]),
            'default_tenant_id' => $user->default_tenant_id,
        ]);
    }

    /**
     * Update the authenticated user's profile.
     *
     * @group Profile
     *
     * @authenticated
     *
     * @bodyParam first_name string required The user's first name. Example: John
     * @bodyParam last_name string required The user's last name. Example: Doe
     * @bodyParam locale string The user's preferred locale (de or en). Example: de
     *
     * @response 200 {
     *   "message": "Profile updated successfully.",
     *   "user": {
     *     "id": 1,
     *     "first_name": "John",
     *     "last_name": "Doe",
     *     "email": "john@example.com",
     *     "locale": "de"
     *   }
     * }
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'locale' => ['nullable', 'string', 'in:de,en'],
        ]);

        $user = $request->user();
        $user->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'locale' => $validated['locale'] ?? $user->locale,
        ]);

        return response()->json([
            'message' => __('profile.updated'),
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'locale' => $user->locale,
            ],
        ]);
    }

    /**
     * Update the authenticated user's password.
     *
     * @group Profile
     *
     * @authenticated
     *
     * @bodyParam current_password string required The current password.
     * @bodyParam password string required The new password (min 8 chars).
     * @bodyParam password_confirmation string required The new password confirmation.
     *
     * @response 200 {
     *   "message": "Password updated successfully."
     * }
     * @response 422 {
     *   "message": "The current password is incorrect.",
     *   "errors": {"current_password": ["The current password is incorrect."]}
     * }
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('auth.password')],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => __('profile.password_updated'),
        ]);
    }
}
