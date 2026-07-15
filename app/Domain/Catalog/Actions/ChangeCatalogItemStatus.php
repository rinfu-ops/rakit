<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Audit\Enums\AuditEventName;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogItem;
use App\Domain\System\Enums\OperationalMode;
use App\Domain\System\Models\SystemOperationalMode;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ChangeCatalogItemStatus
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(User $actor, CatalogItem $catalogItem, CatalogStatus $targetStatus, string $reason): CatalogItem
    {
        Gate::forUser($actor)->authorize('changeStatus', $catalogItem);

        return DB::transaction(function () use ($actor, $catalogItem, $targetStatus, $reason): CatalogItem {
            SystemOperationalMode::query()->lockForUpdate()->findOrFail(1)->mode === OperationalMode::Normal
                || throw new DomainException('Catalog lifecycle changes are blocked while RAKIT is READ_ONLY.');

            $catalogItem = CatalogItem::query()->lockForUpdate()->findOrFail($catalogItem->id);
            if ($catalogItem->status === CatalogStatus::Merged || $targetStatus === CatalogStatus::Merged) {
                throw new DomainException('The MERGED status is controlled by the Catalog merge action.');
            }
            if ($catalogItem->status === $targetStatus) {
                return $catalogItem;
            }

            $beforeStatus = $catalogItem->status->value;
            $catalogItem->forceFill(['status' => $targetStatus])->save();

            $this->recordAuditEvent->handle(
                $actor,
                AuditEventName::CatalogItemUpdated,
                CatalogItem::class,
                (string) $catalogItem->id,
                beforeData: ['status' => $beforeStatus],
                afterData: ['status' => $targetStatus->value],
                context: ['change_type' => 'LIFECYCLE', 'reason' => $reason],
            );

            return $catalogItem;
        });
    }
}
