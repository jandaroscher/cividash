<?php

namespace App\Services\Import;

use App\Http\Requests\Admin\StoreCategoryGroupRequest;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\StoreMetricDefinitionRequest;
use App\Http\Requests\Admin\StoreMetricValueRequest;
use App\Http\Requests\Admin\StoreTileRequest;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Extracts validation rules from the Admin API FormRequests so the import
 * path can reuse them — single source of truth for slug format, length
 * limits, enum values, required keys, etc.
 *
 * The Admin FormRequests read tenant_id from `resolved_tenant` on the
 * request attributes (set by the `resolve.tenant` middleware in HTTP
 * context). We simulate that here so the `Rule::exists(...)->where(...)`
 * constraints scope to the correct tenant during import.
 */
class AdminRuleExtractor
{
    public function __construct(private readonly Tenant $tenant) {}

    /** @return array<string, mixed> */
    public function tileRules(): array
    {
        return $this->extract(StoreTileRequest::class);
    }

    /** @return array<string, mixed> */
    public function categoryGroupRules(): array
    {
        return $this->extract(StoreCategoryGroupRequest::class);
    }

    /** @return array<string, mixed> */
    public function categoryRules(): array
    {
        return $this->extract(StoreCategoryRequest::class);
    }

    /** @return array<string, mixed> */
    public function metricDefinitionRules(): array
    {
        return $this->extract(StoreMetricDefinitionRequest::class);
    }

    /** @return array<string, mixed> */
    public function metricValueRules(): array
    {
        return $this->extract(StoreMetricValueRequest::class);
    }

    /**
     * Instantiate the FormRequest with a simulated resolved_tenant and
     * return its rules array.
     *
     * @param  class-string<FormRequest>  $formRequestClass
     * @return array<string, mixed>
     */
    private function extract(string $formRequestClass): array
    {
        /** @var FormRequest $request */
        $request = new $formRequestClass;
        $request->attributes->set('resolved_tenant', $this->tenant);

        return $request->rules();
    }
}
