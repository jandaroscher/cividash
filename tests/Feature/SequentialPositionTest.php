<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\Tile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers AssignsSequentialPosition: sequential position allocation must work on
 * all drivers, including PostgreSQL where FOR UPDATE + aggregate is illegal and a
 * pg_advisory_lock is used instead (review BLOCKER 1).
 */
class SequentialPositionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Pos Tenant', 'slug' => 'pos-tenant']);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);
        Filament::setTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    public function test_positions_are_assigned_sequentially(): void
    {
        $a = Tile::create(['tenant_id' => $this->tenant->id, 'title' => ['de' => 'A', 'en' => 'A']]);
        $b = Tile::create(['tenant_id' => $this->tenant->id, 'title' => ['de' => 'B', 'en' => 'B']]);
        $c = Tile::create(['tenant_id' => $this->tenant->id, 'title' => ['de' => 'C', 'en' => 'C']]);

        $this->assertSame(1, $a->position);
        $this->assertSame(2, $b->position);
        $this->assertSame(3, $c->position);
    }

    public function test_positions_sequential_when_tenant_id_resolved_implicitly(): void
    {
        // No explicit tenant_id: BelongsToTenant must populate it from the active
        // Filament tenant BEFORE the position is computed, so the max() is scoped
        // to the right tenant. Guards against a trait-boot-order regression.
        $a = Tile::create(['title' => ['de' => 'A', 'en' => 'A']]);
        $b = Tile::create(['title' => ['de' => 'B', 'en' => 'B']]);

        $this->assertSame($this->tenant->id, $a->tenant_id);
        $this->assertSame(1, $a->position);
        $this->assertSame(2, $b->position);
    }

    public function test_explicit_position_is_respected(): void
    {
        $tile = Tile::create(['tenant_id' => $this->tenant->id, 'title' => ['de' => 'X', 'en' => 'X'], 'position' => 42]);

        $this->assertSame(42, $tile->position);
    }

    public function test_failed_insert_does_not_block_subsequent_creates(): void
    {
        // The advisory lock only exists on PostgreSQL, and the insert-failure
        // trigger (over-long varchar) is only enforced there — SQLite ignores
        // declared varchar lengths, so this scenario is PG-specific.
        if (! in_array(DB::connection()->getDriverName(), ['pgsql', 'postgres', 'postgresql'], true)) {
            $this->markTestSkipped('Advisory-lock release on insert failure is a PostgreSQL concern.');
        }

        // The position lock is acquired during the insert; if the INSERT then fails,
        // the `created` event never fires. With the old session-level pg_advisory_lock
        // the lock leaked and blocked later work. performInsert() now wraps the insert
        // in a transaction whose lock is released on rollback, so a failed insert must
        // not wedge the connection: a subsequent create still succeeds.
        try {
            Tile::create([
                'tenant_id' => $this->tenant->id,
                'title' => ['de' => 'Boom', 'en' => 'Boom'],
                'time_granularity' => str_repeat('x', 50), // varchar(10) NOT NULL → insert fails
            ]);
            $this->fail('Expected the over-long time_granularity insert to fail.');
        } catch (\Throwable $e) {
            // expected
        }

        // Connection is not wedged: this create completes and allocates position 1
        // (the failed insert rolled back).
        $ok = Tile::create(['tenant_id' => $this->tenant->id, 'title' => ['de' => 'OK', 'en' => 'OK']]);
        $this->assertSame(1, $ok->position);
    }
}
