<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $organization_id */
class PersonalAccessToken extends \Laravel\Sanctum\PersonalAccessToken
{
    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    protected $hidden = ['token'];
}
