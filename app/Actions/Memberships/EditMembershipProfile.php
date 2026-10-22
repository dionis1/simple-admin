<?php

namespace App\Actions\Memberships;

use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class EditMembershipProfile
{
    public function __invoke(User $actor, int $id, string $displayName, string $jobTitle, ?int $departmentId = null): void
    {
        DB::transaction(function () use ($actor, $id, $displayName, $jobTitle, $departmentId) {
            $org = Organization::query()->lockForUpdate()->findOrFail(app(TenantContext::class)->resolve($actor)->organization_id);
            $member = $org->memberships()->findOrFail($id);
            Gate::forUser($actor)->authorize('update', $member);
            Validator::make(compact('displayName', 'jobTitle'), ['displayName' => ['nullable', 'string', 'max:120'], 'jobTitle' => ['nullable', 'string', 'max:120']])->validate();
            if ($departmentId) {
                Department::where('organization_id', $org->id)->findOrFail($departmentId);
            }
            $member->forceFill(['department_id' => $departmentId, 'display_name' => trim($displayName) ?: null, 'job_title' => trim($jobTitle) ?: null])->save();
        });
    }
}
