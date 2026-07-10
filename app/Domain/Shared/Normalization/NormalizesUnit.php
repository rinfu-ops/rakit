<?php

namespace App\Domain\Shared\Normalization;

interface NormalizesUnit
{
    public function normalize(string $value): string;
}
