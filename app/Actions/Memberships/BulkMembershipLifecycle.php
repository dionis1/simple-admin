<?php

namespace App\Actions\Memberships;

use App\Enums\AccessStatus;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BulkMembershipLifecycle
{
    /** @param array<int, int|string> $ids */
    public function __invoke(User $actor, array $ids, string $status): void
    {
        Validator::make(compact('ids', 'status'), ['ids' => ['required', 'array', 'min:1', 'max:50'], 'ids.*' => ['required', 'integer', 'distinct'], 'status' => [Rule::enum(AccessStatus::class)]])->validate();
        DB::transaction(function () use ($actor, $ids, $status) {
            $org = Organization::query()->lockForUpdate()->findOrFail(app(TenantContext::class)->resolve($actor)->organization_id);
            foreach ($ids as $id) {
                $member = $org->memberships()->findOrFail((int) $id);
                app(UpdateMembership::class)($actor, $member->id, $member->assignedRole()->id ?? 0, $status);
            }
        });
    }
}
