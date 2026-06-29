<?php

namespace App\Services\Import\Support;

/**
 * Per-entity create/update/delete/unchanged counts.
 */
final class ImportDiff
{
    /** @var array<string, array{create:int,update:int,delete:int,unchanged:int}> */
    private array $counts = [];

    public function record(string $entity, string $action): void
    {
        if (! isset($this->counts[$entity])) {
            $this->counts[$entity] = ['create' => 0, 'update' => 0, 'delete' => 0, 'unchanged' => 0];
        }
        if (! array_key_exists($action, $this->counts[$entity])) {
            return;
        }
        $this->counts[$entity][$action]++;
    }

    public function toArray(): array
    {
        ksort($this->counts);

        return $this->counts;
    }
}
