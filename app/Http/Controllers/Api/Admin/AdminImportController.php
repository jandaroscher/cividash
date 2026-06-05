<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportBundleRequest;
use App\Models\Tenant;
use App\Services\Import\ImportService;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin API - Import
 *
 * Bulk-Import von Tiles, Kategorien, Metriken und Jahreswerten per JSON-Bundle.
 * Schema-Definition und Beispiele: siehe docs/upload/schemas/v1/ im Repo.
 */
class AdminImportController extends Controller
{
    public function __construct(private readonly ImportService $importService) {}

    /**
     * Upload + import a bundle
     *
     * Accepts a multipart file upload containing a JSON bundle and either
     * performs a dry-run (preview diff, rollback) or commits the changes.
     * The schema is documented at `docs/upload/schemas/v1/bundle.schema.json`
     * and examples at `docs/upload/examples/`.
     *
     * @authenticated
     *
     * @bodyParam file file required A `.json` bundle (max 20 MB).
     * @bodyParam mode string required Either `dry_run` or `commit`. Example: dry_run
     *
     * @response 200 scenario="Dry-run success" {"mode": "dry_run", "status": "success", "errors": [], "warnings": [], "diff": {"tiles": {"create": 2, "update": 5, "delete": 0, "unchanged": 3}}, "duration_ms": 142}
     * @response 200 scenario="Commit success" {"mode": "commit", "status": "success", "errors": [], "warnings": [], "diff": {"tiles": {"create": 2, "update": 5, "delete": 0, "unchanged": 3}}, "duration_ms": 287}
     * @response 422 scenario="Validation failed" {"mode": "dry_run", "status": "failed", "errors": [{"row": 3, "path": "data[3].tile.title.de", "code": "required", "message": "tile.title.de is required when creating a new tile."}], "warnings": [], "diff": {}, "duration_ms": 58}
     * @response 400 scenario="Missing tenant context" {"message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.", "error": "missing_tenant_context"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     * @response 403 scenario="Missing permission" {"message": "Admin API access denied."}
     */
    public function store(ImportBundleRequest $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('resolved_tenant');
        $userId = $request->user()?->id;

        $result = $this->importService->import(
            file: $request->file('file'),
            tenant: $tenant,
            userId: $userId,
            mode: $request->validated('mode'),
        );

        $status = $result->failed() ? 422 : 200;

        return response()->json($result->toArray(), $status);
    }
}
