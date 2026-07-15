<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\CatalogStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogItem extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'specifications' => 'array',
            'status' => CatalogStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'catalog_category_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CatalogGroup::class, 'catalog_group_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(CatalogAlias::class);
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_catalog_item_id');
    }

    public function mergedPredecessors(): HasMany
    {
        return $this->hasMany(self::class, 'merged_into_catalog_item_id');
    }
}
