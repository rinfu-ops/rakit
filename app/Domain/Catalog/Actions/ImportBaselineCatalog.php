<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Imports\ReadBaselineCatalogWorkbook;
use App\Domain\Catalog\Models\BaselineCatalogItemSource;
use App\Domain\Catalog\Models\CatalogAlias;
use App\Domain\Catalog\Models\CatalogCategory;
use App\Domain\Catalog\Models\CatalogGroup;
use App\Domain\Catalog\Models\CatalogItem;
use App\Domain\Catalog\ValueObjects\BaselineImportReport;
use App\Domain\Pricing\Actions\RecordPriceObservation;
use App\Domain\Shared\Normalization\NormalizesText;
use App\Domain\System\Enums\OperationalMode;
use App\Domain\System\Models\SystemOperationalMode;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ImportBaselineCatalog
{
    public function __construct(
        private readonly ReadBaselineCatalogWorkbook $readWorkbook,
        private readonly CreateCatalogItem $createCatalogItem,
        private readonly RecordPriceObservation $recordPriceObservation,
        private readonly NormalizesText $textNormalizer,
    ) {}

    public function handle(User $actor, string $privatePath, string $sourceId): BaselineImportReport
    {
        Gate::forUser($actor)->authorize('importBaseline', CatalogItem::class);
        if (! preg_match('/^[A-Z0-9][A-Z0-9._-]{2,63}$/', $sourceId)) {
            throw new InvalidArgumentException('The source ID is invalid.');
        }
        $rows = $this->readWorkbook->handle($privatePath);

        return DB::transaction(function () use ($actor, $rows, $sourceId): BaselineImportReport {
            $mode = SystemOperationalMode::query()->lockForUpdate()->findOrFail(1);
            if ($mode->mode !== OperationalMode::Normal) {
                throw new DomainException('Baseline import is blocked while RAKIT is READ_ONLY.');
            }

            $createdItems = $existingItems = $aliases = $prices = 0;
            foreach ($rows as $row) {
                $category = $this->resolveDimension(CatalogCategory::class, $row['category_code'], $row['category_name']);
                $group = $this->resolveDimension(CatalogGroup::class, $row['group_code'], $row['group_name']);
                $mapping = BaselineCatalogItemSource::query()->where('source_id', $sourceId)->where('source_item_id', $row['source_item_id'])->first();

                if ($mapping === null) {
                    $item = $this->createCatalogItem->handle($actor, $sourceId, $row, $category->id, $group->id);
                    $createdItems++;
                } else {
                    if (! hash_equals($mapping->content_hash, $row['content_hash'])) {
                        throw new DomainException('A baseline master item changed after import.');
                    }
                    $item = CatalogItem::query()->findOrFail($mapping->catalog_item_id);
                    $existingItems++;
                }

                if ($row['alias'] !== null && $this->recordAlias($actor, $item, $sourceId, $row)) {
                    $aliases++;
                }
                if ($row['unit_price_rupiah'] !== null && $this->recordPriceObservation->handle($actor, $item, $sourceId, $row)) {
                    $prices++;
                }
            }

            return new BaselineImportReport(count($rows), $createdItems, $existingItems, $aliases, $prices);
        });
    }

    private function resolveDimension(string $modelClass, string $code, string $name): CatalogCategory|CatalogGroup
    {
        $model = $modelClass::query()->where('code', $code)->first();
        if ($model !== null) {
            if ($model->name !== $name) {
                throw new DomainException("Dimension {$code} has conflicting names.");
            }

            return $model;
        }
        $model = new $modelClass;
        $model->forceFill(['code' => $code, 'name' => $name])->save();

        return $model;
    }

    /** @param array<string, mixed> $row */
    private function recordAlias(User $actor, CatalogItem $item, string $sourceId, array $row): bool
    {
        $reference = $sourceId.':'.$row['source_line_id'];
        $normalized = $this->textNormalizer->normalize($row['alias']);
        $alias = CatalogAlias::query()->where('source_type', 'BASELINE_IMPORT')->where('source_reference', $reference)->first();
        if ($alias !== null) {
            if ($alias->catalog_item_id !== $item->id || $alias->normalized_description !== $normalized) {
                throw new DomainException('A baseline alias source line changed after import.');
            }

            return false;
        }
        (new CatalogAlias)->forceFill([
            'catalog_item_id' => $item->id, 'raw_description' => $row['alias'],
            'normalized_description' => $normalized, 'source_type' => 'BASELINE_IMPORT',
            'source_reference' => $reference, 'approved_by' => $actor->id, 'approved_at' => now(),
        ])->save();

        return true;
    }
}
