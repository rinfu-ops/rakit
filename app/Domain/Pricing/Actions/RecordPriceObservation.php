<?php

namespace App\Domain\Pricing\Actions;

use App\Domain\Catalog\Models\CatalogItem;
use App\Domain\Pricing\Models\PriceObservation;
use App\Domain\System\Enums\OperationalMode;
use App\Domain\System\Models\SystemOperationalMode;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;

class RecordPriceObservation
{
    /** @param array<string, mixed> $row */
    public function handle(User $actor, CatalogItem $catalogItem, string $sourceId, array $row): bool
    {
        Gate::forUser($actor)->authorize('importBaseline', CatalogItem::class);
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Price Observation recording requires an active transaction.');
        }
        if (SystemOperationalMode::query()->findOrFail(1)->mode !== OperationalMode::Normal) {
            throw new DomainException('Price recording is blocked while RAKIT is READ_ONLY.');
        }

        $existing = PriceObservation::query()
            ->where('source_type', 'BASELINE_IMPORT')->where('source_id', $sourceId)
            ->where('source_line_id', $row['source_line_id'])->whereBelongsTo($catalogItem)->first();

        $attributes = [
            'unit_price_rupiah' => $row['unit_price_rupiah'], 'quantity' => $row['quantity'],
            'normalized_unit' => $catalogItem->normalized_unit, 'currency_code' => 'IDR',
            'price_basis' => $row['price_basis'], 'tax_context' => $row['tax_context'],
            'observed_at' => $row['observed_at'], 'source_type' => 'BASELINE_IMPORT',
            'source_id' => $sourceId, 'source_line_id' => $row['source_line_id'], 'guidance_eligible' => true,
        ];

        if ($existing !== null) {
            $matches = (int) $existing->unit_price_rupiah === $attributes['unit_price_rupiah']
                && $existing->quantity === $attributes['quantity']
                && $existing->normalized_unit === $attributes['normalized_unit']
                && $existing->currency_code === 'IDR'
                && $existing->price_basis === $attributes['price_basis']
                && $existing->tax_context === $attributes['tax_context']
                && $existing->observed_at->toDateString() === $attributes['observed_at']->toDateString();

            if (! $matches) {
                throw new DomainException('A baseline price source line changed after import.');
            }

            return false;
        }

        (new PriceObservation)->forceFill(['catalog_item_id' => $catalogItem->id, ...$attributes])->save();

        return true;
    }
}
