<?php

namespace App\Domain\System\Enums;

enum OperationalMode: string
{
    case Normal = 'NORMAL';
    case ReadOnly = 'READ_ONLY';
}
