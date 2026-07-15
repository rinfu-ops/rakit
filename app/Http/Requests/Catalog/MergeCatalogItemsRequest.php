<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Models\CatalogItem;
use Illuminate\Foundation\Http\FormRequest;

class MergeCatalogItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('merge', $this->route('catalogItem')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'successor_catalog_code' => ['required', 'string', 'max:255', 'exists:catalog_items,catalog_code'],
            'reason' => ['required', 'string', 'between:5,500'],
        ];
    }

    public function successor(): CatalogItem
    {
        return CatalogItem::query()->where('catalog_code', $this->validated('successor_catalog_code'))->firstOrFail();
    }
}
