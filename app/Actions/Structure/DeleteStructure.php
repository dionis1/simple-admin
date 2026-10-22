<?php

namespace App\Actions\Structure;

use App\Models\Department;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteStructure
{
    public function __invoke(User $actor, string $type, int $id): void
    {
        abort_unless(in_array($type, ['team', 'department'], true), 422);
        DB::transaction(function () use ($actor, $type, $id) {
            $org = app(TenantContext::class)->resolve($actor)->organization_id;
            Organization::query()->lockForUpdate()->findOrFail($org);
            $resource = $type === 'team' ? Team::where('organization_id', $org)->findOrFail($id) : Department::where('organization_id', $org)->findOrFail($id);
            Gate::forUser($actor)->authorize('delete', $resource);
            if ($type === 'department') {
                Membership::where('organization_id', $org)->where('department_id', $id)->update(['department_id' => null]);
                Team::where('organization_id', $org)->where('department_id', $id)->update(['department_id' => null]);
            }
            $resource->delete();
        });
    }
}
