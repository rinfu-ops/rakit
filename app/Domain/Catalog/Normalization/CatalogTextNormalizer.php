<?php

namespace App\Domain\Catalog\Normalization;

use App\Domain\Shared\Normalization\NormalizesText;
use Illuminate\Support\Str;

class CatalogTextNormalizer implements NormalizesText
{
    public function normalize(string $value): string
    {
        return Str::of($value)->squish()->lower()->toString();
    }
}
