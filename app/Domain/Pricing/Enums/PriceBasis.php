<?php

namespace App\Domain\Pricing\Enums;

enum PriceBasis: string
{
    case RapCost = 'RAP_COST';
    case SellingPrice = 'SELLING_PRICE';
    case VendorQuote = 'VENDOR_QUOTE';
    case BudgetEstimate = 'BUDGET_ESTIMATE';
}
