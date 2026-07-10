<?php

namespace App\Domain\System\Enums;

enum UserRole: string
{
    public const DEFAULT_ROLE = 'VIEWER';

    case Admin = 'ADMIN';
    case CatalogManager = 'CATALOG_MANAGER';
    case RapEditor = 'RAP_EDITOR';
    case Reviewer = 'REVIEWER';
    case Viewer = self::DEFAULT_ROLE;
}
