<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use App\Tenancy\TenantContext;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return app(TenantContext::class)->allows($user, 'departments.view');
    }

    public function create(User $user): bool
    {
        return app(TenantContext::class)->allows($user, 'departments.manage');
    }

    public function update(User $user, Department $department): bool
    {
        return app(TenantContext::class)->current($user)?->organization_id === $department->organization_id && $this->create($user);
    }

    public function delete(User $user, Department $department): bool
    {
        return $this->update($user, $department);
    }
}
