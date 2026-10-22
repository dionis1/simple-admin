<?php

namespace App\Actions\Organizations;

use App\Enums\AccessStatus;
use App\Enums\RoleName;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CreateOrganization
{
    public function __invoke(User $actor, string $name): Organization
    {
        Gate::forUser($actor)->authorize('create', Organization::class);
        $validated = Validator::make(['name' => trim($name)], ['name' => ['required', 'string', 'max:120']])->validate();

        return DB::transaction(function () use ($actor, $validated) {
            User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($actor)->authorize('create', Organization::class);
            $organization = Organization::create($validated);
            $membership = new Membership;
            $membership->forceFill(['organization_id' => $organization->id, 'user_id' => $actor->id, 'status' => AccessStatus::Active])->save();

            foreach (RoleName::cases() as $name) {
                $role = new Role;
                $role->forceFill(['organization_id' => $organization->id, 'name' => $name])->save();
                $keys = array_map(fn ($permission) => $permission->value, $name->permissions());
                $role->permissions()->sync(Permission::whereIn('key', $keys)->pluck('id'));

                if ($name === RoleName::Owner) {
                    $assignment = new RoleAssignment;
                    $assignment->forceFill(['organization_id' => $organization->id, 'membership_id' => $membership->id, 'role_id' => $role->id])->save();
                }
            }

            return $organization;
        });
    }
}
