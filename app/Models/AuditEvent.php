<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $actor_id
 * @property string $event
 * @property string $target_type
 * @property string|null $target_id
 * @property string $correlation_id
 * @property array<string, mixed> $changes
 * @property Carbon $occurred_at
 */
class AuditEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'occurred_at' => 'datetime'];
    }
}
