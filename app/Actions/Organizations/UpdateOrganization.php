<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdateOrganization
{
    public function __invoke(User $actor, string $name): void
    {
        DB::transaction(function () use ($actor, $name) {
            $id = app(TenantContext::class)->resolve($actor)->organization_id;
            $organization = Organization::query()->lockForUpdate()->findOrFail($id);
            Gate::forUser($actor)->authorize('update', $organization);
            $validated = Validator::make(['name' => trim($name)], ['name' => ['required', 'string', 'max:120']])->validate();
            $organization->update($validated);
        });
    }
}
