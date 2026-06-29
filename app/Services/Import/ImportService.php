<?php

namespace App\Services\Import;

use App\Models\ImportRun;
use App\Models\Tenant;
use App\Services\Import\Support\ImportDiff;
use App\Services\Import\Support\ImportError;
use App\Services\Import\Support\ImportResult;
use Illuminate\Http\UploadedFile;

/**
 * Facade for the import workflow:
 *   1. decode + parse
 *   2. schema-validate (structural)
 *   3. BundleImporter run (fachlich + transaction)
 *   4. persist an ImportRun audit entry
 */
class ImportService
{
    public function __construct(
        private readonly SchemaValidator $schemaValidator,
    ) {}

    public function import(UploadedFile $file, Tenant $tenant, ?int $userId, string $mode): ImportResult
    {
        $raw = $file->get();
        $started = microtime(true);

        try {
            $bundle = json_decode($raw, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $result = new ImportResult(
                mode: $mode,
                status: 'failed',
                diff: new ImportDiff,
                errors: [new ImportError(
                    path: 'bundle',
                    code: 'invalid_json',
                    message: 'Uploaded file is not valid JSON: '.$e->getMessage(),
                )],
                durationMs: (int) ((microtime(true) - $started) * 1000),
            );
            $this->persistRun($tenant, $userId, $mode, $file, $result);

            return $result;
        }

        if (! is_array($bundle)) {
            $result = new ImportResult(
                mode: $mode,
                status: 'failed',
                diff: new ImportDiff,
                errors: [new ImportError(
                    path: 'bundle',
                    code: 'invalid_type',
                    message: 'Bundle root must be a JSON object.',
                )],
                durationMs: (int) ((microtime(true) - $started) * 1000),
            );
            $this->persistRun($tenant, $userId, $mode, $file, $result);

            return $result;
        }

        $structuralErrors = $this->schemaValidator->validate($bundle);
        if (! empty($structuralErrors)) {
            $result = new ImportResult(
                mode: $mode,
                status: 'failed',
                diff: new ImportDiff,
                errors: $structuralErrors,
                durationMs: (int) ((microtime(true) - $started) * 1000),
            );
            $this->persistRun($tenant, $userId, $mode, $file, $result);

            return $result;
        }

        $importer = new BundleImporter($tenant);
        $result = $importer->run($bundle, $mode);

        $this->persistRun($tenant, $userId, $mode, $file, $result);

        return $result;
    }

    /**
     * Persist the audit row as best-effort. The BundleImporter has already
     * committed (or rolled back) its own transaction by the time we get here
     * — if audit persistence throws, we must not flip a successful commit
     * into a 500 for the caller. Log the failure and continue.
     */
    private function persistRun(Tenant $tenant, ?int $userId, string $mode, UploadedFile $file, ImportResult $result): void
    {
        try {
            ImportRun::withoutEvents(function () use ($tenant, $userId, $mode, $file, $result) {
                $run = new ImportRun([
                    'tenant_id' => $tenant->id,
                    'user_id' => $userId,
                    'mode' => $mode,
                    'format' => 'json',
                    'filename' => $file->getClientOriginalName() ?: 'upload.json',
                    'byte_size' => $file->getSize() ?: 0,
                    'status' => $result->status,
                    'diff_summary' => $result->diff->toArray(),
                    'error_count' => count($result->errors),
                    'warning_count' => count($result->warnings),
                    'duration_ms' => $result->durationMs,
                ]);
                $run->tenant()->associate($tenant);
                $run->save();
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to persist ImportRun audit entry', [
                'tenant_id' => $tenant->id,
                'mode' => $mode,
                'filename' => $file->getClientOriginalName(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);
        }
    }
}
