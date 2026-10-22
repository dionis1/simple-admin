<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $team_id
 * @property int $membership_id
 * @property-read Membership $membership
 */
class TeamMembership extends Model
{
    /** @return BelongsTo<Membership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public $timestamps = false;

    protected $guarded = ['*'];
}
