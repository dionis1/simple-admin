<?php

namespace App\Tenancy;

use App\Enums\AccessStatus;
use App\Enums\RoleName;
use App\Models\Membership;
use App\Models\Team;
use Illuminate\Validation\ValidationException;

class OwnerProtection
{
    // Call only inside a transaction holding this organization's FOR UPDATE lock.
    public function ensureMayChange(Membership $target, ?RoleName $nextRole, ?AccessStatus $nextStatus): void
    {
        if (($nextRole === null || $nextStatus !== AccessStatus::Active) && Team::where('owner_membership_id', $target->id)->exists()) {
            throw ValidationException::withMessages(['membership' => __('Transfer team ownership first.')]);
        }
        if ($target->status !== AccessStatus::Active || $target->assignedRole()?->name !== RoleName::Owner) {
            return;
        }

        if ($nextRole === RoleName::Owner && $nextStatus === AccessStatus::Active) {
            return;
        }

        $anotherOwner = Membership::query()->where('organization_id', $target->organization_id)
            ->where('id', '!=', $target->id)->where('status', AccessStatus::Active)
            ->whereHas('user', fn ($q) => $q->where('status', AccessStatus::Active))
            ->whereHas('roleAssignment.role', fn ($query) => $query->where('name', RoleName::Owner))->exists();

        if (! $anotherOwner) {
            throw ValidationException::withMessages(['membership' => __('An organization must retain an active owner.')]);
        }
    }
}
