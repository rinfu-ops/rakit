<?php

namespace App\Domain\Catalog\Enums;

enum CatalogStatus: string
{
    case Active = 'ACTIVE';
    case Deprecated = 'DEPRECATED';
    case Merged = 'MERGED';
    case Inactive = 'INACTIVE';
}
