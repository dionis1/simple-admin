<?php

namespace App\Actions\Structure;

use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaveDepartment
{
    public function __invoke(User $actor, int $id, string $name): Department
    {
        return DB::transaction(function () use ($actor, $id, $name) {
            $org = app(TenantContext::class)->resolve($actor)->organization_id;
            Organization::query()->lockForUpdate()->findOrFail($org);
            $department = $id ? Department::where('organization_id', $org)->findOrFail($id) : new Department;
            Gate::forUser($actor)->authorize($id ? 'update' : 'create', $id ? $department : Department::class);
            Validator::make(['departmentName' => trim($name)], ['departmentName' => ['required', 'string', 'max:120', Rule::unique('departments', 'name')->where('organization_id', $org)->ignore($id)]])->validate();
            $department->forceFill(['organization_id' => $org, 'name' => trim($name)])->save();

            return $department;
        });
    }
}
