<?php

namespace App\Domain\Catalog\ValueObjects;

final readonly class BaselineImportReport
{
    public function __construct(
        public int $sourceRows,
        public int $catalogItemsCreated,
        public int $sourceRowsReconciled,
        public int $aliasesCreated,
        public int $pricesCreated,
    ) {}
}
