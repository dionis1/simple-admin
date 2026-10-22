<?php

namespace App\Models;

use App\Governance\TransactionalAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $team_id
 * @property int $membership_id
 * @property-read Membership $membership
 */
class TeamMembership extends Model
{
    use TransactionalAudit;

    /** @return BelongsTo<Membership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public $timestamps = false;

    protected $guarded = ['*'];
}
