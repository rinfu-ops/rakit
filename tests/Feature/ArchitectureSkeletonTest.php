<?php

namespace Tests\Feature;

use App\Domain\Audit\Enums\AuditEventName;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Shared\Money\IdrAmount;
use App\Domain\System\Actions\ChangeOperationalMode;
use App\Domain\System\Enums\OperationalMode;
use App\Domain\System\Enums\UserRole;
use App\Domain\System\Models\SystemOperationalMode;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class ArchitectureSkeletonTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_users_default_to_viewer_and_initial_roles_are_explicit(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::Viewer, $user->role);
        $this->assertSame([
            'ADMIN',
            'CATALOG_MANAGER',
            'RAP_EDITOR',
            'REVIEWER',
            'VIEWER',
        ], array_column(UserRole::cases(), 'value'));
    }

    public function test_postgresql_creates_exactly_one_normal_operational_mode_singleton(): void
    {
        $operationalMode = SystemOperationalMode::query()->sole();

        $this->assertSame(1, $operationalMode->id);
        $this->assertSame(OperationalMode::Normal, $operationalMode->mode);
    }

    public function test_viewer_is_denied_operational_mode_changes_while_admin_is_allowed(): void
    {
        $viewer = User::factory()->create();
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        $this->assertFalse($viewer->can('change', SystemOperationalMode::class));
        $this->assertTrue($admin->can('change', SystemOperationalMode::class));

        $this->expectException(AuthorizationException::class);

        app(ChangeOperationalMode::class)->handle($viewer, OperationalMode::ReadOnly, 'Synthetic test');
    }

    public function test_operational_mode_changes_are_audited_and_events_are_append_only(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        $operationalMode = app(ChangeOperationalMode::class)->handle(
            $admin,
            OperationalMode::ReadOnly,
            'Synthetic integrity test',
        );

        $this->assertSame(OperationalMode::ReadOnly, $operationalMode->mode);
        $this->assertSame('Synthetic integrity test', $operationalMode->reason);

        $auditEvent = AuditEvent::query()->sole();

        $this->assertSame($admin->id, $auditEvent->actor_id);
        $this->assertSame(AuditEventName::SystemOperationalModeChanged, $auditEvent->event_name);
        $this->assertSame(['mode' => 'NORMAL'], $auditEvent->before_data);
        $this->assertSame(['mode' => 'READ_ONLY'], $auditEvent->after_data);
        $this->assertSame(['reason' => 'Synthetic integrity test'], $auditEvent->context);

        app(ChangeOperationalMode::class)->handle(
            $admin,
            OperationalMode::ReadOnly,
            'No-op integrity test',
        );

        $this->assertSame(1, AuditEvent::query()->count());

        $this->expectException(LogicException::class);

        $auditEvent->save();
    }

    public function test_sensitive_fields_cannot_be_generically_mass_assigned(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();
        $operationalMode = app(ChangeOperationalMode::class)->handle(
            $admin,
            OperationalMode::ReadOnly,
            'Synthetic integrity test',
        );

        $this->expectException(MassAssignmentException::class);

        $operationalMode->update([
            'mode' => OperationalMode::ReadOnly,
            'reason' => 'Untrusted input',
        ]);
    }

    public function test_generic_user_mass_assignment_cannot_promote_a_viewer_to_admin(): void
    {
        $viewer = User::factory()->create();

        $viewer->update(['role' => UserRole::Admin]);

        $this->assertSame(UserRole::Viewer, $viewer->fresh()->role);
    }

    public function test_idr_amount_accepts_integer_rupiah_and_rejects_invalid_values(): void
    {
        $this->assertSame(1500, IdrAmount::fromRupiah(1500)->rupiah);
        $this->assertSame(0, IdrAmount::fromRupiah(0)->rupiah);

        foreach ([1.5, '1500', -1] as $invalidAmount) {
            try {
                IdrAmount::fromRupiah($invalidAmount);
                $this->fail('Invalid IDR amount was accepted.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_postgresql_rejects_invalid_phase_two_constraint_values(): void
    {
        $user = User::factory()->create();

        $this->assertPostgresRejects(fn () => DB::table((new User)->getTable())
            ->where('id', $user->id)
            ->update(['role' => 'INVALID_ROLE']));

        $this->assertPostgresRejects(fn () => DB::table((new AuditEvent)->getTable())->insert([
            'event_name' => 'INVALID_EVENT',
            'subject_type' => SystemOperationalMode::class,
            'subject_id' => '1',
        ]));

        $this->assertPostgresRejects(fn () => DB::table((new SystemOperationalMode)->getTable())
            ->where('id', 1)
            ->update(['mode' => 'INVALID_MODE']));

        $this->assertPostgresRejects(fn () => DB::table((new SystemOperationalMode)->getTable())->insert([
            'id' => 2,
            'mode' => OperationalMode::Normal->value,
        ]));
    }

    public function test_postgresql_rejects_audit_event_query_mutation_and_singleton_deletion(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();
        app(ChangeOperationalMode::class)->handle(
            $admin,
            OperationalMode::ReadOnly,
            'Synthetic integrity test',
        );
        $auditEvent = AuditEvent::query()->sole();

        $this->assertPostgresRejects(fn () => DB::table((new AuditEvent)->getTable())
            ->where('id', $auditEvent->id)
            ->update(['subject_id' => 'changed']));

        $this->assertPostgresRejects(fn () => DB::table((new AuditEvent)->getTable())
            ->where('id', $auditEvent->id)
            ->delete());

        $this->assertPostgresRejects(fn () => DB::table((new SystemOperationalMode)->getTable())
            ->where('id', 1)
            ->delete());
    }

    private function assertPostgresRejects(Closure $operation): void
    {
        try {
            DB::transaction($operation);
            $this->fail('PostgreSQL accepted an invalid database mutation.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_application_uses_jakarta_timezone_and_normal_mode_default(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame(OperationalMode::Normal->value, config('rakit.operational_mode.default'));
    }
}
