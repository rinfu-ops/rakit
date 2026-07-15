<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\ValueObjects\CatalogSpecifications;
use App\Rules\CatalogSpecificationsJson;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('catalogItem')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'catalog_category_id' => ['nullable', 'integer', 'exists:catalog_categories,id'],
            'standard_name' => ['required', 'string', 'max:1000'],
            'standard_description' => ['nullable', 'string', 'max:5000'],
            'normalized_unit' => ['required', 'string', 'max:100'],
            'specifications_json' => ['bail', 'nullable', 'string', 'max:20000', new CatalogSpecificationsJson],
        ];
    }

    /** @return array<string, mixed> */
    public function catalogMetadata(): array
    {
        $validated = $this->validated();

        return [
            'catalog_category_id' => $validated['catalog_category_id'] ?? null,
            'standard_name' => trim($validated['standard_name']),
            'standard_description' => isset($validated['standard_description']) ? trim($validated['standard_description']) : null,
            'normalized_unit' => trim($validated['normalized_unit']),
            'specifications' => CatalogSpecifications::fromJson($validated['specifications_json'] ?? null)->value,
        ];
    }
}
