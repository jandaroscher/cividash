<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves the upload-bundle schemas and example files from docs/upload/ as
 * downloadable assets so end-users (and external integrators) can retrieve
 * them without repository access.
 *
 * Public (no auth) so that third-party tooling — e.g. a VS Code JSON-schema
 * $ref, an NGSI-LD adapter, or an Excel-to-JSON converter — can consume them.
 * The files are static copies of what's already in the open-source repo.
 *
 * @group Admin API - Import
 */
class ImportSchemaController extends Controller
{
    private const SCHEMA_ALLOWLIST = [
        'bundle' => 'docs/upload/schemas/v1/bundle.schema.json',
        'row' => 'docs/upload/schemas/v1/row.schema.json',
        'category-group' => 'docs/upload/schemas/v1/category-group.schema.json',
        'category' => 'docs/upload/schemas/v1/category.schema.json',
    ];

    private const EXAMPLE_ALLOWLIST = [
        'valid-minimal' => 'docs/upload/examples/valid-minimal.json',
        'valid-full' => 'docs/upload/examples/valid-full.json',
        'valid-structure-only' => 'docs/upload/examples/valid-structure-only.json',
    ];

    /**
     * Download a schema file
     *
     * Serves a JSON Schema (draft-07) describing the upload bundle format.
     * Use `bundle` for the root schema; the others are referenced from it.
     *
     * @urlParam name string required Schema name (bundle, row, category-group, category). Example: bundle
     */
    public function schema(Request $request, string $name): BinaryFileResponse
    {
        return $this->serve(self::SCHEMA_ALLOWLIST, $name, "{$name}.schema.json");
    }

    /**
     * Download an example bundle
     *
     * Serves a ready-to-use valid example bundle. Useful as a starting point
     * for custom imports.
     *
     * @urlParam name string required Example name (valid-minimal, valid-full, valid-structure-only). Example: valid-minimal
     */
    public function example(Request $request, string $name): BinaryFileResponse
    {
        return $this->serve(self::EXAMPLE_ALLOWLIST, $name, "{$name}.json");
    }

    /**
     * @param  array<string, string>  $allowlist
     */
    private function serve(array $allowlist, string $name, string $downloadAs): BinaryFileResponse
    {
        if (! isset($allowlist[$name])) {
            throw new NotFoundHttpException("Unknown resource: {$name}");
        }

        $path = base_path($allowlist[$name]);
        if (! is_file($path)) {
            throw new NotFoundHttpException("File missing: {$name}");
        }

        return response()->download(
            $path,
            $downloadAs,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }
}
