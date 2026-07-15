<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Audit\Enums\AuditEventName;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogCategory;
use App\Domain\Catalog\Models\CatalogItem;
use App\Domain\Shared\Normalization\NormalizesText;
use App\Domain\Shared\Normalization\NormalizesUnit;
use App\Domain\System\Enums\OperationalMode;
use App\Domain\System\Models\SystemOperationalMode;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateCatalogItemMetadata
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NormalizesText $textNormalizer,
        private readonly NormalizesUnit $unitNormalizer,
    ) {}

    /**
     * @param  array{
     *     catalog_category_id: int|null,
     *     standard_name: string,
     *     standard_description: string|null,
     *     normalized_unit: string,
     *     specifications: array<mixed>|object
     * }  $metadata
     */
    public function handle(User $actor, CatalogItem $catalogItem, array $metadata): CatalogItem
    {
        Gate::forUser($actor)->authorize('update', $catalogItem);

        return DB::transaction(function () use ($actor, $catalogItem, $metadata): CatalogItem {
            SystemOperationalMode::query()->lockForUpdate()->findOrFail(1)->mode === OperationalMode::Normal
                || throw new DomainException('Catalog mutation is blocked while RAKIT is READ_ONLY.');

            $catalogItem = CatalogItem::query()->lockForUpdate()->findOrFail($catalogItem->id);
            if ($catalogItem->status === CatalogStatus::Merged) {
                throw new DomainException('Merged Catalog Item metadata cannot be changed.');
            }
            if ($metadata['catalog_category_id'] !== null) {
                CatalogCategory::query()->findOrFail($metadata['catalog_category_id']);
            }

            $beforeData = $this->auditableMetadata($catalogItem);
            $catalogItem->forceFill([
                'catalog_category_id' => $metadata['catalog_category_id'],
                'standard_name' => $metadata['standard_name'],
                'normalized_name' => $this->textNormalizer->normalize($metadata['standard_name']),
                'standard_description' => $metadata['standard_description'],
                'normalized_unit' => $this->unitNormalizer->normalize($metadata['normalized_unit']),
                'specifications' => $metadata['specifications'],
            ])->save();

            $afterData = $this->auditableMetadata($catalogItem);
            if ($beforeData !== $afterData) {
                $this->recordAuditEvent->handle(
                    $actor,
                    AuditEventName::CatalogItemUpdated,
                    CatalogItem::class,
                    (string) $catalogItem->id,
                    beforeData: $beforeData,
                    afterData: $afterData,
                    context: ['change_type' => 'METADATA'],
                );
            }

            return $catalogItem;
        });
    }

    /** @return array<string, mixed> */
    private function auditableMetadata(CatalogItem $catalogItem): array
    {
        return [
            'catalog_category_id' => $catalogItem->catalog_category_id,
            'standard_name' => $catalogItem->standard_name,
            'standard_description' => $catalogItem->standard_description,
            'normalized_unit' => $catalogItem->normalized_unit,
            'specifications' => $catalogItem->specifications,
        ];
    }
}
