<?php

namespace App\Models;

use App\Enums\AccessStatus;
use App\Governance\TransactionalAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string|null $display_name
 * @property string|null $job_title
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property AccessStatus $status
 * @property-read RoleAssignment|null $roleAssignment
 * @property-read User $user
 * @property-read Organization $organization
 */
class Membership extends Model
{
    use TransactionalAudit;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['status' => AccessStatus::class];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<RoleAssignment, $this> */
    public function roleAssignment(): HasOne
    {
        return $this->hasOne(RoleAssignment::class);
    }

    public function assignedRole(): ?Role
    {
        return Role::query()->where('organization_id', $this->organization_id)
            ->whereHas('assignments', fn ($query) => $query->where('membership_id', $this->id))->first();
    }
}
