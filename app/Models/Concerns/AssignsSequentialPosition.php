<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Assigns a sequential `position` (max+1, scoped by a column) on creation,
 * serialised so two concurrent inserts cannot allocate the same position.
 *
 * There is no UNIQUE(scope, position) constraint to fall back on, so the
 * max(position)+1 read and the following INSERT must be serialised against each
 * other. This concern does that by:
 *
 *  1. overriding performInsert() to wrap the WHOLE insert (the `creating`/
 *     `created` events AND the INSERT statement) in one transaction, and
 *  2. computing the position inside a `creating` listener, which fires within
 *     that transaction and AFTER BelongsToTenant has populated the scope column.
 *
 * - PostgreSQL forbids "FOR UPDATE" with aggregate functions, so it takes a
 *   TRANSACTION-scoped advisory lock (pg_advisory_xact_lock). Because the lock
 *   lives inside the same transaction that performs the INSERT, it spans the
 *   read+insert window and is released automatically on COMMIT *or* ROLLBACK —
 *   so it can never leak, even if the read or the INSERT throws.
 * - MySQL/MariaDB/SQLite use SELECT ... max(position) ... FOR UPDATE inside the
 *   same transaction for the equivalent guarantee.
 */
trait AssignsSequentialPosition
{
    public static function bootAssignsSequentialPosition(): void
    {
        // Fires inside the transaction opened by performInsert(), after the
        // BelongsToTenant `creating` listener has set the scope column.
        static::creating(function (Model $model): void {
            $model->assignSequentialPosition();
        });
    }

    /**
     * The column that scopes the position sequence (e.g. tenant_id).
     */
    abstract protected function positionScopeColumn(): string;

    /**
     * Resolve the scope value used to compute max(position). Independent of the
     * order in which model `creating` listeners fire: if the scope is tenant_id
     * and BelongsToTenant has not populated it yet, fall back to the resolved
     * current tenant so the max() query is always scoped correctly.
     */
    protected function positionScopeValue(string $scopeColumn): mixed
    {
        $value = $this->{$scopeColumn};

        if ($value === null && $scopeColumn === 'tenant_id' && method_exists($this, 'resolveTenant')) {
            $value = static::resolveTenant()?->id;
        }

        return $value;
    }

    protected function performInsert(Builder $query)
    {
        // Explicit position, or not running our own allocation: normal insert.
        if (! is_null($this->position)) {
            return parent::performInsert($query);
        }

        // Wrap the entire insert (creating event + INSERT) in one transaction so
        // the lock taken in assignSequentialPosition() spans the INSERT and is
        // released on commit/rollback — no advisory-lock leak is possible.
        return $this->getConnection()->transaction(fn () => parent::performInsert($query));
    }

    /**
     * Allocate the next position under a lock. Runs inside performInsert()'s
     * transaction via the `creating` listener.
     */
    protected function assignSequentialPosition(): void
    {
        if (! is_null($this->position)) {
            return;
        }

        $scopeColumn = $this->positionScopeColumn();
        $scopeValue = $this->positionScopeValue($scopeColumn);
        $connection = $this->getConnection();

        if (in_array($connection->getDriverName(), ['pgsql', 'postgres', 'postgresql'], true)) {
            $connection->statement('SELECT pg_advisory_xact_lock(?)', [$this->advisoryLockKey($scopeColumn, $scopeValue)]);

            $this->position = (int) static::query()
                ->where($scopeColumn, $scopeValue)
                ->max('position') + 1;

            return;
        }

        $this->position = (int) static::query()
            ->where($scopeColumn, $scopeValue)
            ->lockForUpdate()
            ->max('position') + 1;
    }

    /**
     * Stable key (<= 60 bits, fits a Postgres bigint; sign is irrelevant — PG
     * advisory-lock keys may be negative) derived from table + scope so distinct
     * scopes never contend on the same advisory lock.
     */
    private function advisoryLockKey(string $scopeColumn, mixed $scopeValue): int
    {
        $material = $this->getTable().':'.$scopeColumn.':'.(string) $scopeValue;

        return (int) hexdec(substr(hash('sha256', $material), 0, 15));
    }
}
