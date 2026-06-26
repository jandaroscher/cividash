<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Assigns a sequential `position` (max+1, scoped by a column) on creation,
 * serialised so two concurrent inserts cannot allocate the same position.
 *
 * There is no UNIQUE(scope, position) constraint to fall back on, so the
 * max(position)+1 read and the following INSERT must be serialised.
 *
 * - PostgreSQL forbids "FOR UPDATE" with aggregate functions. Instead we take a
 *   SESSION-level advisory lock (pg_advisory_lock) in `creating`, keyed on
 *   table+scope, and release it in `created` — i.e. AFTER the INSERT — so the
 *   lock spans the read+insert window. (Session-level, not xact-level, because
 *   Eloquent's INSERT runs outside the model's own transaction; a transaction-
 *   scoped lock would release before the INSERT.)
 * - MySQL/MariaDB/SQLite keep the original SELECT ... FOR UPDATE inside a
 *   transaction wrapping the max() read.
 */
trait AssignsSequentialPosition
{
    protected ?int $sequentialPositionLockKey = null;

    public static function bootAssignsSequentialPosition(): void
    {
        static::creating(function (Model $model): void {
            $model->acquireSequentialPosition();
        });

        static::created(function (Model $model): void {
            $model->releaseSequentialPositionLock();
        });
    }

    /**
     * The column that scopes the position sequence (e.g. tenant_id).
     */
    abstract protected function positionScopeColumn(): string;

    protected function acquireSequentialPosition(): void
    {
        if (! is_null($this->position)) {
            return;
        }

        $scopeColumn = $this->positionScopeColumn();
        $scopeValue = $this->{$scopeColumn};
        $connection = $this->getConnection();
        $driver = $connection->getDriverName();

        if (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
            // Session-level advisory lock, released in created() after the INSERT.
            $this->sequentialPositionLockKey = $this->advisoryLockKey($scopeColumn, $scopeValue);
            $connection->statement('SELECT pg_advisory_lock(?)', [$this->sequentialPositionLockKey]);

            $this->position = (int) static::query()
                ->where($scopeColumn, $scopeValue)
                ->max('position') + 1;

            return;
        }

        // MySQL/MariaDB/SQLite: FOR UPDATE inside a short transaction.
        $connection->transaction(function () use ($scopeColumn, $scopeValue): void {
            $this->position = (int) static::query()
                ->where($scopeColumn, $scopeValue)
                ->lockForUpdate()
                ->max('position') + 1;
        });
    }

    protected function releaseSequentialPositionLock(): void
    {
        if ($this->sequentialPositionLockKey === null) {
            return;
        }

        $this->getConnection()->statement('SELECT pg_advisory_unlock(?)', [$this->sequentialPositionLockKey]);
        $this->sequentialPositionLockKey = null;
    }

    /**
     * Stable signed 63-bit key from table + scope so distinct scopes never
     * contend on the same advisory lock.
     */
    private function advisoryLockKey(string $scopeColumn, mixed $scopeValue): int
    {
        $material = $this->getTable().':'.$scopeColumn.':'.(string) $scopeValue;

        return (int) hexdec(substr(hash('sha256', $material), 0, 15));
    }
}
