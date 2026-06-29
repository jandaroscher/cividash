<?php

namespace App\Services\Import;

use App\Services\Import\Support\ImportError;
use JsonSchema\Constraints\Constraint;
use JsonSchema\SchemaStorage;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;

/**
 * Structural validation of an upload bundle against the JSON Schemas in
 * docs/upload/schemas/v1/. Returns an array of ImportError — empty array
 * means the bundle is structurally valid (domain-level validation runs separately).
 */
class SchemaValidator
{
    private const SCHEMA_DIR = 'docs/upload/schemas/v1';

    private const SCHEMA_PREFIX = 'file://';

    /**
     * @param  object|array<mixed>  $bundle  Decoded JSON (use json_decode with assoc=false for objects).
     * @return list<ImportError>
     */
    public function validate(object|array $bundle): array
    {
        $bundleObj = $this->toObject($bundle);

        $storage = new SchemaStorage(new UriRetriever);
        $base = base_path(self::SCHEMA_DIR);

        // Register all schemas by their file:// URI so that `$ref: "row.schema.json"`
        // inside bundle.schema.json resolves to a pre-registered URI instead of
        // attempting a network fetch.
        $mainUri = self::SCHEMA_PREFIX.$base.'/bundle.schema.json';
        foreach (['bundle', 'row', 'category-group', 'category'] as $name) {
            $uri = self::SCHEMA_PREFIX.$base."/{$name}.schema.json";
            $storage->addSchema($uri, json_decode(file_get_contents($base."/{$name}.schema.json")));
        }

        $validator = new Validator(new \JsonSchema\Constraints\Factory($storage));
        $validator->validate(
            $bundleObj,
            (object) ['$ref' => $mainUri],
            Constraint::CHECK_MODE_TYPE_CAST | Constraint::CHECK_MODE_APPLY_DEFAULTS
        );

        if ($validator->isValid()) {
            return [];
        }

        return array_map(
            fn (array $e) => new ImportError(
                path: $this->normalisePath($e['property'] ?? ''),
                code: $this->mapConstraint($this->constraintName($e['constraint'] ?? 'schema')),
                message: $e['message'] ?? 'Schema validation failed.',
                row: $this->extractRow($e['property'] ?? ''),
            ),
            $validator->getErrors(),
        );
    }

    /**
     * Convert associative arrays to stdClass so justinrainbow/json-schema
     * can distinguish "object" from "array".
     */
    private function toObject(mixed $value): mixed
    {
        return json_decode(json_encode($value, JSON_THROW_ON_ERROR), false);
    }

    /**
     * justinrainbow paths look like `data[0].tile.title.de` — strip the
     * leading `.` that the library sometimes adds.
     */
    private function normalisePath(string $property): string
    {
        return ltrim($property, '.');
    }

    /**
     * Extract `N` from `data[N].…` so the error can be grouped per row.
     */
    private function extractRow(string $property): ?int
    {
        if (preg_match('/^data\[(\d+)\]/', ltrim($property, '.'), $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Newer versions of justinrainbow/json-schema emit the constraint as an
     * associative array `{name, params}`. Coerce both shapes into a string.
     */
    private function constraintName(mixed $constraint): string
    {
        if (is_string($constraint)) {
            return $constraint;
        }

        if (is_array($constraint) && isset($constraint['name']) && is_string($constraint['name'])) {
            return $constraint['name'];
        }

        return 'schema';
    }

    private function mapConstraint(string $constraint): string
    {
        return match ($constraint) {
            'required' => 'required',
            'enum' => 'invalid_enum_value',
            'type' => 'invalid_type',
            'pattern' => 'invalid_format',
            'format' => 'invalid_format',
            'minimum', 'maximum', 'minLength', 'maxLength' => 'out_of_range',
            'additionalProperties' => 'unknown_property',
            default => 'schema_'.$constraint,
        };
    }
}
