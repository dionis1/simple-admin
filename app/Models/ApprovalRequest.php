<?php

namespace App\Models;

use App\Governance\TransactionalAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $requester_id
 * @property int|null $reviewer_id
 * @property string $operation
 * @property string $state
 * @property string $reason
 * @property array<string, int> $payload
 * @property Carbon $expires_at
 */
class ApprovalRequest extends Model
{
    use TransactionalAudit;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'expires_at' => 'datetime', 'decided_at' => 'datetime', 'executed_at' => 'datetime'];
    }
}
