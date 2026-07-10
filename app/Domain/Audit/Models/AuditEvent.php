<?php

namespace App\Domain\Audit\Models;

use App\Domain\Audit\Enums\AuditEventName;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'event_name' => AuditEventName::class,
            'before_data' => 'array',
            'after_data' => 'array',
            'context' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Audit Events are append-only.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new LogicException('Audit Events cannot be deleted.');
    }
}
