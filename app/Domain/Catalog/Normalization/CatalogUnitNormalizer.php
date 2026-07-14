<?php

namespace App\Domain\Catalog\Normalization;

use App\Domain\Shared\Normalization\NormalizesUnit;
use Illuminate\Support\Str;

class CatalogUnitNormalizer implements NormalizesUnit
{
    public function normalize(string $value): string
    {
        return Str::of($value)
            ->squish()
            ->lower()
            ->replace(['²', '³', '×'], ['2', '3', 'x'])
            ->replaceMatches('/\s+/', '')
            ->toString();
    }
}
