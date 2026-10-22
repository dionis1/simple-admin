<?php

use App\Enums\RoleName;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('rejects cross tenant role assignment even when application authorization is bypassed', function (string $source) {
    [$owner, $organization] = tenantWorkspace();
    [, $foreign] = tenantWorkspace();
    $membership = $organization->memberships()->firstOrFail();
    $foreignRole = tenantRole($foreign, RoleName::Owner);
    $data = $source === 'role'
        ? ['role_id' => $foreignRole->id]
        : ['organization_id' => $foreign->id];

    expect(fn () => DB::transaction(fn () => DB::table('role_assignments')->where('membership_id', $membership->id)->update($data)))
        ->toThrow(QueryException::class);
    expect($membership->assignedRole()->organization_id)->toBe($organization->id);
})->with(['role', 'organization']);

it('enforces unique organization membership role and assignment identities', function (string $constraint) {
    [$owner, $organization] = tenantWorkspace();
    $membership = $organization->memberships()->firstOrFail();
    $role = tenantRole($organization, RoleName::Owner);
    [$table, $data] = match ($constraint) {
        'membership' => ['memberships', ['organization_id' => $organization->id, 'user_id' => $owner->id]],
        'role' => ['roles', ['organization_id' => $organization->id, 'name' => 'owner']],
        'assignment' => ['role_assignments', ['organization_id' => $organization->id, 'membership_id' => $membership->id, 'role_id' => $role->id]],
        'permission' => ['permissions', ['key' => 'dashboard.view', 'resource' => 'dashboard', 'action' => 'view']],
    };
    expect(fn () => DB::transaction(fn () => DB::table($table)->insert($data)))->toThrow(QueryException::class);
})->with(['membership', 'role', 'assignment', 'permission']);

it('rejects invalid status and role names at the database boundary', function (string $table) {
    [$owner, $organization] = tenantWorkspace();
    $column = $table === 'roles' ? 'name' : 'status';
    expect(fn () => DB::transaction(fn () => DB::table($table)->update([$column => 'invalid'])))->toThrow(QueryException::class);
})->with(['organizations', 'memberships', 'roles']);

it('prevents raw user deletion from bypassing membership ownership checks', function () {
    [$owner] = tenantWorkspace();
    expect(fn () => DB::transaction(fn () => DB::table('users')->where('id', $owner->id)->delete()))->toThrow(QueryException::class);
    $this->assertDatabaseHas('users', ['id' => $owner->id]);
});
