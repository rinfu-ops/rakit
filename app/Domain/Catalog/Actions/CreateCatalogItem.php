<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Audit\Enums\AuditEventName;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\BaselineCatalogItemSource;
use App\Domain\Catalog\Models\CatalogItem;
use App\Domain\System\Enums\OperationalMode;
use App\Domain\System\Models\SystemOperationalMode;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;

class CreateCatalogItem
{
    public function __construct(
        private readonly GenerateCatalogCode $generateCatalogCode,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /** @param array<string, mixed> $item */
    public function handle(User $actor, string $sourceId, array $item, int $categoryId, int $groupId): CatalogItem
    {
        Gate::forUser($actor)->authorize('importBaseline', CatalogItem::class);
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Baseline Catalog creation requires an active transaction.');
        }
        if (SystemOperationalMode::query()->findOrFail(1)->mode !== OperationalMode::Normal) {
            throw new DomainException('Catalog mutation is blocked while RAKIT is READ_ONLY.');
        }

        $allocation = $this->generateCatalogCode->reserveLocked(
            $item['source_item_id'],
            $item['discipline_code'],
            $item['item_type_code'],
            $item['group_code'],
        );

        $catalogItem = new CatalogItem;
        $catalogItem->forceFill([
            'catalog_code' => $allocation->catalogCode,
            'discipline_code' => $item['discipline_code'],
            'item_type_code' => $item['item_type_code'],
            'catalog_category_id' => $categoryId,
            'catalog_group_id' => $groupId,
            'sequence_number' => $allocation->sequenceNumber,
            'standard_name' => $item['standard_name'],
            'normalized_name' => $item['normalized_name'],
            'standard_description' => $item['standard_description'],
            'normalized_unit' => $item['normalized_unit'],
            'specifications' => [],
            'status' => CatalogStatus::Active,
            'approved_at' => now(),
        ])->save();

        $mapping = new BaselineCatalogItemSource;
        $mapping->forceFill([
            'source_id' => $sourceId,
            'source_item_id' => $item['source_item_id'],
            'catalog_item_id' => $catalogItem->id,
            'content_hash' => $item['content_hash'],
        ])->save();

        $this->recordAuditEvent->handle(
            $actor,
            AuditEventName::CatalogItemCreated,
            CatalogItem::class,
            (string) $catalogItem->id,
            afterData: ['catalog_code' => $catalogItem->catalog_code],
            context: ['source_type' => 'BASELINE_IMPORT', 'source_id' => $sourceId, 'source_item_id' => $item['source_item_id']],
        );

        return $catalogItem;
    }
}
