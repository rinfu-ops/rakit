<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Enums\CatalogStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeCatalogItemStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('changeStatus', $this->route('catalogItem')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(CatalogStatus::class), Rule::notIn([CatalogStatus::Merged->value])],
            'reason' => ['required', 'string', 'between:5,500'],
        ];
    }
}
