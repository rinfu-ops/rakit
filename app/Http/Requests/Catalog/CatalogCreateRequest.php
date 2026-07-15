<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Models\CatalogItem;
use Illuminate\Foundation\Http\FormRequest;

class CatalogCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CatalogItem::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $candidateName = $this->input('candidate_name');
        if (is_string($candidateName)) {
            $this->merge(['candidate_name' => trim($candidateName)]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'candidate_name' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function candidateName(): string
    {
        return $this->validated('candidate_name') ?? '';
    }
}
