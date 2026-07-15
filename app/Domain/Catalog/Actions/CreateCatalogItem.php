<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Audit\Enums\AuditEventName;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\BaselineCatalogItemSource;
use App\Domain\Catalog\Models\CatalogCategory;
use App\Domain\Catalog\Models\CatalogGroup;
use App\Domain\Catalog\Models\CatalogItem;
use App\Domain\Catalog\Queries\FindCatalogDuplicateCandidates;
use App\Domain\Shared\Normalization\NormalizesText;
use App\Domain\Shared\Normalization\NormalizesUnit;
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
        private readonly FindCatalogDuplicateCandidates $findDuplicateCandidates,
        private readonly NormalizesText $textNormalizer,
        private readonly NormalizesUnit $unitNormalizer,
    ) {}

    /**
     * @param  array{
     *     discipline_code: string,
     *     item_type_code: string,
     *     catalog_category_id: int|null,
     *     catalog_group_id: int,
     *     standard_name: string,
     *     standard_description: string|null,
     *     normalized_unit: string,
     *     specifications: array<mixed>|object,
     *     duplicate_reviewed: bool
     * }  $item
     */
    public function handle(User $actor, array $item): CatalogItem
    {
        Gate::forUser($actor)->authorize('create', CatalogItem::class);

        return DB::transaction(function () use ($actor, $item): CatalogItem {
            $this->ensureCatalogMutationIsAllowed(lock: true);

            if (! $item['duplicate_reviewed'] && $this->findDuplicateCandidates->handle($item['standard_name'])->isNotEmpty()) {
                throw new DomainException('Review the possible duplicate Catalog Items before creating a distinct identity.');
            }

            $group = CatalogGroup::query()->findOrFail($item['catalog_group_id']);
            if ($item['catalog_category_id'] !== null) {
                CatalogCategory::query()->findOrFail($item['catalog_category_id']);
            }

            $allocation = $this->generateCatalogCode->handle(
                $item['discipline_code'],
                $item['item_type_code'],
                $group->code,
            );

            $catalogItem = $this->persist([
                'discipline_code' => $item['discipline_code'],
                'item_type_code' => $item['item_type_code'],
                'catalog_category_id' => $item['catalog_category_id'],
                'catalog_group_id' => $group->id,
                'catalog_code' => $allocation->catalogCode,
                'sequence_number' => $allocation->sequenceNumber,
                'standard_name' => $item['standard_name'],
                'normalized_name' => $this->textNormalizer->normalize($item['standard_name']),
                'standard_description' => $item['standard_description'],
                'normalized_unit' => $this->unitNormalizer->normalize($item['normalized_unit']),
                'specifications' => $item['specifications'],
            ]);

            $this->recordCreatedAuditEvent($actor, $catalogItem, ['source_type' => 'CATALOG_MANAGEMENT']);

            return $catalogItem;
        });
    }

    /** @param array<string, mixed> $item */
    public function handleBaseline(User $actor, string $sourceId, array $item, int $categoryId, int $groupId): CatalogItem
    {
        Gate::forUser($actor)->authorize('importBaseline', CatalogItem::class);
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Baseline Catalog creation requires an active transaction.');
        }
        $this->ensureCatalogMutationIsAllowed();

        $allocation = $this->generateCatalogCode->reserveLocked(
            $item['source_item_id'],
            $item['discipline_code'],
            $item['item_type_code'],
            $item['group_code'],
        );

        $catalogItem = $this->persist([
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
        ]);

        $mapping = new BaselineCatalogItemSource;
        $mapping->forceFill([
            'source_id' => $sourceId,
            'source_item_id' => $item['source_item_id'],
            'catalog_item_id' => $catalogItem->id,
            'content_hash' => $item['content_hash'],
        ])->save();

        $this->recordCreatedAuditEvent($actor, $catalogItem, [
            'source_type' => 'BASELINE_IMPORT',
            'source_id' => $sourceId,
            'source_item_id' => $item['source_item_id'],
        ]);

        return $catalogItem;
    }

    /** @param array<string, mixed> $attributes */
    private function persist(array $attributes): CatalogItem
    {
        $catalogItem = new CatalogItem;
        $catalogItem->forceFill([
            ...$attributes,
            'status' => CatalogStatus::Active,
            'approved_at' => now(),
        ])->save();

        return $catalogItem;
    }

    /** @param array<string, mixed> $context */
    private function recordCreatedAuditEvent(User $actor, CatalogItem $catalogItem, array $context): void
    {
        $this->recordAuditEvent->handle(
            $actor,
            AuditEventName::CatalogItemCreated,
            CatalogItem::class,
            (string) $catalogItem->id,
            afterData: ['catalog_code' => $catalogItem->catalog_code, 'status' => $catalogItem->status->value],
            context: $context,
        );
    }

    private function ensureCatalogMutationIsAllowed(bool $lock = false): void
    {
        $query = SystemOperationalMode::query();
        if ($lock) {
            $query->lockForUpdate();
        }
        if ($query->findOrFail(1)->mode !== OperationalMode::Normal) {
            throw new DomainException('Catalog mutation is blocked while RAKIT is READ_ONLY.');
        }
    }
}
