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

class MergeCatalogItems
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(User $actor, CatalogItem $source, CatalogItem $successor, string $reason): CatalogItem
    {
        Gate::forUser($actor)->authorize('merge', $source);
        Gate::forUser($actor)->authorize('merge', $successor);
        if ($source->is($successor)) {
            throw new DomainException('A Catalog Item cannot be merged into itself.');
        }

        return DB::transaction(function () use ($actor, $source, $successor, $reason): CatalogItem {
            SystemOperationalMode::query()->lockForUpdate()->findOrFail(1)->mode === OperationalMode::Normal
                || throw new DomainException('Catalog merges are blocked while RAKIT is READ_ONLY.');

            $items = CatalogItem::query()
                ->whereKey([$source->id, $successor->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $source = $items->get($source->id) ?? throw new DomainException('The merge source no longer exists.');
            $successor = $items->get($successor->id) ?? throw new DomainException('The merge successor no longer exists.');

            if ($source->status === CatalogStatus::Merged) {
                throw new DomainException('A merged Catalog Item cannot be merged again.');
            }
            if ($successor->status !== CatalogStatus::Active) {
                throw new DomainException('The merge successor must be ACTIVE.');
            }

            $beforeData = [
                'status' => $source->status->value,
                'successor_id' => $source->merged_into_catalog_item_id,
            ];

            $source->forceFill([
                'status' => CatalogStatus::Merged,
                'merged_into_catalog_item_id' => $successor->id,
            ])->save();

            $this->recordAuditEvent->handle(
                $actor,
                AuditEventName::CatalogItemsMerged,
                CatalogItem::class,
                (string) $source->id,
                beforeData: $beforeData,
                afterData: ['status' => CatalogStatus::Merged->value, 'successor_id' => $successor->id],
                context: ['reason' => $reason],
            );

            return $source;
        });
    }
}
