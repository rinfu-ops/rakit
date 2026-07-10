<?php

namespace App\Domain\Audit\Actions;

use App\Domain\Audit\Enums\AuditEventName;
use App\Domain\Audit\Models\AuditEvent;
use App\Models\User;

class RecordAuditEvent
{
    /**
     * @param  array<string, mixed>|null  $beforeData
     * @param  array<string, mixed>|null  $afterData
     * @param  array<string, mixed>|null  $context
     */
    public function handle(
        ?User $actor,
        AuditEventName $eventName,
        string $subjectType,
        string $subjectId,
        ?array $beforeData = null,
        ?array $afterData = null,
        ?array $context = null,
    ): AuditEvent {
        $auditEvent = new AuditEvent;
        $auditEvent->forceFill([
            'actor_id' => $actor?->getKey(),
            'event_name' => $eventName,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before_data' => $beforeData,
            'after_data' => $afterData,
            'context' => $context,
        ]);
        $auditEvent->save();

        return $auditEvent;
    }
}
