<?php

namespace App\Domain\Audit\Enums;

enum AuditEventName: string
{
    case CatalogItemCreated = 'CATALOG_ITEM_CREATED';
    case CatalogItemUpdated = 'CATALOG_ITEM_UPDATED';
    case CatalogItemsMerged = 'CATALOG_ITEMS_MERGED';
    case ImportMappingApproved = 'IMPORT_MAPPING_APPROVED';
    case ImportFinalized = 'IMPORT_FINALIZED';
    case RapSubmitted = 'RAP_SUBMITTED';
    case RapReturned = 'RAP_RETURNED';
    case RapApproved = 'RAP_APPROVED';
    case RapFinalized = 'RAP_FINALIZED';
    case RapRevisionCreated = 'RAP_REVISION_CREATED';
    case PriceObservationVoided = 'PRICE_OBSERVATION_VOIDED';
    case TemplateVersionCreated = 'TEMPLATE_VERSION_CREATED';
    case TemplateActivated = 'TEMPLATE_ACTIVATED';
    case SystemOperationalModeChanged = 'SYSTEM_OPERATIONAL_MODE_CHANGED';
}
