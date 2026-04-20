<?php

namespace App\Http\Requests\Api;

use App\Services\Export\ExportQuery;
use App\Services\Export\FieldWhitelist;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class ExportQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['nullable', 'string', 'in:json,csv'],
            'locale' => ['nullable', 'string', 'in:de,en'],
            'fields' => ['nullable', 'array'],
            'fields.*' => ['string'],
            'tiles' => ['nullable', 'array'],
            'tiles.*' => ['integer', 'min:1'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string'],
            'year_from' => ['nullable', 'integer', 'min:1900', 'max:2200'],
            'year_to' => ['nullable', 'integer', 'min:1900', 'max:2200', 'gte:year_from'],
        ];
    }

    public function toExportQuery(?string $tileSlug = null): ExportQuery
    {
        $format = $this->query('format', ExportQuery::FORMAT_JSON);
        $locale = $this->resolveLocale();

        /** @var list<string> $requestedFields */
        $requestedFields = $this->query('fields', []);
        if (! is_array($requestedFields)) {
            $requestedFields = [];
        }

        $fields = $requestedFields === []
            ? FieldWhitelist::defaults()
            : FieldWhitelist::filter(array_values(array_map('strval', $requestedFields)));

        if ($fields === []) {
            throw new HttpResponseException(new JsonResponse([
                'message' => trans('export.errors.no_valid_fields', [], $locale),
            ], 422));
        }

        $tileIds = array_values(array_map('intval', (array) $this->query('tiles', [])));
        $categoryKeys = array_values(array_map('strval', (array) $this->query('categories', [])));
        $yearFrom = $this->query('year_from');
        $yearTo = $this->query('year_to');

        return new ExportQuery(
            format: $format,
            locale: $locale,
            fields: $fields,
            tileSlug: $tileSlug,
            tileIds: $tileIds,
            categoryKeys: $categoryKeys,
            yearFrom: $yearFrom !== null ? (int) $yearFrom : null,
            yearTo: $yearTo !== null ? (int) $yearTo : null,
        );
    }

    protected function failedValidation(Validator $validator): void
    {
        $locale = $this->resolveLocale();

        throw new HttpResponseException(new JsonResponse([
            'message' => trans('export.errors.invalid_query', [], $locale),
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }

    /**
     * Pick the locale for user-visible error messages. Falls back to German
     * to match the default audience of the dashboard.
     */
    private function resolveLocale(): string
    {
        $locale = $this->query('locale');

        return in_array($locale, ['de', 'en'], true) ? $locale : 'de';
    }
}
