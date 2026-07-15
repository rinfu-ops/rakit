<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CatalogItem::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::enum(CatalogStatus::class)],
            'category_id' => ['nullable', 'integer', 'exists:catalog_categories,id'],
            'group_id' => ['nullable', 'integer', 'exists:catalog_groups,id'],
        ];
    }
}
