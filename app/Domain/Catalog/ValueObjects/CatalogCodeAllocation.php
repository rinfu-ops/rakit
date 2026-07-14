<?php

namespace App\Domain\Catalog\ValueObjects;

final readonly class CatalogCodeAllocation
{
    public function __construct(public string $catalogCode, public int $sequenceNumber) {}
}
