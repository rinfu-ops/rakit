<?php

namespace App\Domain\Pricing\Enums;

enum TaxContext: string
{
    case Exclusive = 'EXCLUSIVE';
    case Inclusive = 'INCLUSIVE';
    case Unknown = 'UNKNOWN';
}
