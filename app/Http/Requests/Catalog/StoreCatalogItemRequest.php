<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Models\CatalogItem;
use App\Domain\Catalog\ValueObjects\CatalogSpecifications;
use App\Rules\CatalogSpecificationsJson;
use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CatalogItem::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'discipline_code' => strtoupper(trim((string) $this->input('discipline_code'))),
            'item_type_code' => strtoupper(trim((string) $this->input('item_type_code'))),
            'duplicate_reviewed' => $this->boolean('duplicate_reviewed'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'discipline_code' => ['required', 'string', 'between:2,20', 'regex:/^[A-Z0-9]+$/'],
            'item_type_code' => ['required', 'string', 'between:2,20', 'regex:/^[A-Z0-9]+$/'],
            'catalog_category_id' => ['nullable', 'integer', 'exists:catalog_categories,id'],
            'catalog_group_id' => ['required', 'integer', 'exists:catalog_groups,id'],
            'standard_name' => ['required', 'string', 'max:1000'],
            'standard_description' => ['nullable', 'string', 'max:5000'],
            'normalized_unit' => ['required', 'string', 'max:100'],
            'specifications_json' => ['bail', 'nullable', 'string', 'max:20000', new CatalogSpecificationsJson],
            'duplicate_reviewed' => ['boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function catalogData(): array
    {
        $validated = $this->validated();

        return [
            'discipline_code' => $validated['discipline_code'],
            'item_type_code' => $validated['item_type_code'],
            'catalog_category_id' => $validated['catalog_category_id'] ?? null,
            'catalog_group_id' => $validated['catalog_group_id'],
            'standard_name' => trim($validated['standard_name']),
            'standard_description' => isset($validated['standard_description']) ? trim($validated['standard_description']) : null,
            'normalized_unit' => trim($validated['normalized_unit']),
            'specifications' => CatalogSpecifications::fromJson($validated['specifications_json'] ?? null)->value,
            'duplicate_reviewed' => $validated['duplicate_reviewed'],
        ];
    }
}
