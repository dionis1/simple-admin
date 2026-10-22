<?php

namespace App\Models;

use App\Governance\TransactionalAudit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $organization_id */
class PersonalAccessToken extends \Laravel\Sanctum\PersonalAccessToken
{
    use TransactionalAudit;

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    protected $hidden = ['token'];
}
