<?php

namespace App\Models;

use App\Governance\TransactionalAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $actor_id
 * @property int $approval_request_id
 * @property int $target_membership_id
 * @property string|null $session_hash
 * @property Carbon $expires_at
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 */
class SupportGrant extends Model
{
    use TransactionalAudit;

    protected $guarded = ['*'];

    protected $hidden = ['session_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'started_at' => 'datetime', 'ended_at' => 'datetime'];
    }
}
