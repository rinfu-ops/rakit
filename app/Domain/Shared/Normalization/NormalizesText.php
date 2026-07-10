<?php

namespace App\Domain\Shared\Normalization;

interface NormalizesText
{
    public function normalize(string $value): string;
}
