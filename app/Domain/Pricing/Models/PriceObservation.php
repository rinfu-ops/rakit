<?php

namespace App\Domain\Pricing\Models;

use App\Domain\Catalog\Models\CatalogItem;
use App\Domain\Pricing\Enums\PriceBasis;
use App\Domain\Pricing\Enums\TaxContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceObservation extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'price_basis' => PriceBasis::class,
            'tax_context' => TaxContext::class,
            'observed_at' => 'date',
            'guidance_eligible' => 'boolean',
            'voided_at' => 'datetime',
        ];
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }
}
