<?php

namespace App\Models;

use App\Governance\TransactionalAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $id
 * @property int $organization_id
 * @property int $owner_membership_id
 * @property int|null $department_id
 * @property string $name
 * @property-read Membership $owner
 * @property-read Department|null $department
 */
class Team extends Model
{
    use TransactionalAudit;

    protected $guarded = ['*'];

    /** @return BelongsTo<Membership, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'owner_membership_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
