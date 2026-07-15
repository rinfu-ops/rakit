<?php

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogItem;
use App\Domain\Shared\Normalization\NormalizesText;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SearchCatalogItems
{
    public function __construct(private readonly NormalizesText $textNormalizer) {}

    /**
     * @param  array{query?: string|null, status?: string|null, category_id?: int|null, group_id?: int|null}  $filters
     * @return LengthAwarePaginator<int, CatalogItem>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $query = CatalogItem::query()
            ->with(['category', 'group', 'successor'])
            ->withCount('aliases');

        $searchTerm = trim((string) ($filters['query'] ?? ''));
        if ($searchTerm !== '') {
            $normalized = $this->textNormalizer->normalize($searchTerm);
            $query->where(function (Builder $candidateQuery) use ($searchTerm, $normalized): void {
                $candidateQuery
                    ->whereRaw('catalog_code ILIKE ?', [$searchTerm])
                    ->orWhere('normalized_name', $normalized)
                    ->orWhereRaw('normalized_name % ?', [$normalized])
                    ->orWhereHas('aliases', fn (Builder $aliasQuery) => $aliasQuery
                        ->where('normalized_description', $normalized)
                        ->orWhereRaw('normalized_description % ?', [$normalized]));
            })->orderByRaw('CASE WHEN catalog_code ILIKE ? THEN 0 WHEN normalized_name = ? THEN 1 ELSE 2 END', [$searchTerm, $normalized])
                ->orderByRaw('similarity(normalized_name, ?) DESC', [$normalized]);
        }

        if (($filters['status'] ?? null) !== null) {
            $query->where('status', $filters['status']);
        }
        if (($filters['category_id'] ?? null) !== null) {
            $query->where('catalog_category_id', $filters['category_id']);
        }
        if (($filters['group_id'] ?? null) !== null) {
            $query->where('catalog_group_id', $filters['group_id']);
        }

        if ($searchTerm === '') {
            $query->orderByRaw("CASE status WHEN 'ACTIVE' THEN 0 WHEN 'DEPRECATED' THEN 1 WHEN 'INACTIVE' THEN 2 ELSE 3 END")
                ->orderBy('catalog_code');
        } else {
            $query->orderBy('catalog_code');
        }

        return $query->paginate(20)->withQueryString();
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return collect(CatalogStatus::cases())->mapWithKeys(fn (CatalogStatus $status): array => [
            $status->value => ucfirst(strtolower($status->value)),
        ])->all();
    }
}
