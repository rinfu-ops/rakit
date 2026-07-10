<?php

namespace App\Domain\System\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Audit\Enums\AuditEventName;
use App\Domain\System\Enums\OperationalMode;
use App\Domain\System\Models\SystemOperationalMode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ChangeOperationalMode
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(User $actor, OperationalMode $mode, string $reason): SystemOperationalMode
    {
        Gate::forUser($actor)->authorize('change', SystemOperationalMode::class);

        if (trim($reason) === '') {
            throw new InvalidArgumentException('An operational-mode change requires a reason.');
        }

        return DB::transaction(function () use ($actor, $mode, $reason): SystemOperationalMode {
            $operationalMode = SystemOperationalMode::query()
                ->lockForUpdate()
                ->findOrFail(1);

            if ($operationalMode->mode === $mode) {
                return $operationalMode;
            }

            $beforeData = ['mode' => $operationalMode->mode->value];

            $operationalMode->forceFill([
                'mode' => $mode,
                'reason' => $reason,
                'changed_by' => $actor->getKey(),
                'changed_at' => now(),
            ]);
            $operationalMode->save();

            $this->recordAuditEvent->handle(
                actor: $actor,
                eventName: AuditEventName::SystemOperationalModeChanged,
                subjectType: SystemOperationalMode::class,
                subjectId: (string) $operationalMode->getKey(),
                beforeData: $beforeData,
                afterData: ['mode' => $mode->value],
                context: ['reason' => $reason],
            );

            return $operationalMode;
        });
    }
}
