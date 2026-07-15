<?php

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogItem;
use App\Domain\Shared\Normalization\NormalizesText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FindCatalogDuplicateCandidates
{
    public function __construct(private readonly NormalizesText $textNormalizer) {}

    /** @return Collection<int, CatalogItem> */
    public function handle(string $standardName, int $limit = 8): Collection
    {
        $normalized = $this->textNormalizer->normalize(trim($standardName));
        if (mb_strlen($normalized) < 3) {
            return new Collection;
        }

        return CatalogItem::query()
            ->with(['category', 'group'])
            ->whereIn('status', [CatalogStatus::Active, CatalogStatus::Deprecated])
            ->where(function (Builder $query) use ($normalized): void {
                $query->whereRaw('similarity(normalized_name, ?) >= 0.35', [$normalized])
                    ->orWhereHas('aliases', fn (Builder $aliasQuery) => $aliasQuery
                        ->whereRaw('similarity(normalized_description, ?) >= 0.35', [$normalized]));
            })
            ->orderByRaw('similarity(normalized_name, ?) DESC', [$normalized])
            ->orderBy('catalog_code')
            ->limit($limit)
            ->get();
    }
}
