<?php

namespace App\Domain\System\Models;

use App\Domain\System\Enums\OperationalMode;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemOperationalMode extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'mode' => OperationalMode::class,
            'changed_at' => 'datetime',
        ];
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
