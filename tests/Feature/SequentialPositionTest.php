<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\Tile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        // No advisory lock left held after the inserts (PostgreSQL only).
        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'postgres', 'postgresql'], true)) {
            $held = DB::selectOne("select count(*) as c from pg_locks where locktype = 'advisory'")->c;
            $this->assertSame(0, (int) $held, 'advisory lock leaked after insert');
        }
    }

    public function test_explicit_position_is_respected(): void
    {
        $tile = Tile::create(['tenant_id' => $this->tenant->id, 'title' => ['de' => 'X', 'en' => 'X'], 'position' => 42]);

        $this->assertSame(42, $tile->position);
    }
}
