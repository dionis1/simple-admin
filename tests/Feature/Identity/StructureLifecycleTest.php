<?php

use App\Actions\Memberships\BulkMembershipLifecycle;
use App\Actions\Memberships\EditMembershipProfile;
use App\Actions\Memberships\RemoveMembership;
use App\Actions\Memberships\UpdateMembership;
use App\Actions\Security\AccountLifecycle;
use App\Actions\Structure\AssignTeamMember;
use App\Actions\Structure\DeleteStructure;
use App\Actions\Structure\SaveDepartment;
use App\Actions\Structure\SaveTeam;
use App\Enums\AccessStatus;
use App\Enums\RoleName;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('creates departments teams active owners and tenant membership assignments', function () {
    [$owner,$org] = tenantWorkspace();
    tenantActor($owner, $org);
    $department = app(SaveDepartment::class)($owner, 0, 'Engineering');
    $own = $org->memberships()->firstOrFail();
    $team = app(SaveTeam::class)($owner, 0, 'Platform', $own->id, $department->id);
    $member = tenantMember($owner, $org, RoleName::Viewer);
    app(AssignTeamMember::class)($owner, $team->id, $member->id);
    expect(TeamMembership::where('team_id', $team->id)->count())->toBe(2);
    app(EditMembershipProfile::class)($owner, $member->id, 'Local name', 'Engineer', $department->id);
    expect($member->refresh()->display_name)->toBe('Local name')->and($member->getAttribute('department_id'))->toBe($department->id);
    app(DeleteStructure::class)($owner, 'department', $department->id);
    expect($team->refresh()->department_id)->toBeNull()->and($member->refresh()->getAttribute('department_id'))->toBeNull();
});

it('requires team ownership transfer before membership suspension removal or account deactivation', function (string $operation) {
    [$owner,$org] = tenantWorkspace();
    $member = tenantMember($owner, $org, RoleName::Owner);
    $user = $member->user()->firstOrFail();
    tenantActor($owner, $org);
    $team = app(SaveTeam::class)($owner, 0, 'Owned team', $member->id, null);
    $change = match ($operation) {
        'suspend' => fn () => app(UpdateMembership::class)($owner, $member->id, $member->assignedRole()->id, 'suspended'),
        'remove' => fn () => app(RemoveMembership::class)($owner, $member->id),
        'account' => fn () => app(AccountLifecycle::class)($user, 'password', false),
    };
    expect($change)->toThrow(ValidationException::class);
    $own = $org->memberships()->where('user_id', $owner->id)->firstOrFail();
    app(SaveTeam::class)($owner, $team->id, $team->name, $own->id, null);
    app(UpdateMembership::class)($owner, $member->id, $member->assignedRole()->id, 'deactivated');
    expect($member->refresh()->status)->toBe(AccessStatus::Deactivated);
})->with(['suspend', 'remove', 'account']);

it('rejects foreign or inactive team owners departments and assignments', function (string $kind) {
    [$owner,$org] = tenantWorkspace();
    [, $foreign] = tenantWorkspace();
    $foreignMember = $foreign->memberships()->firstOrFail();
    tenantActor($owner, $org);
    $own = $org->memberships()->firstOrFail();
    $team = app(SaveTeam::class)($owner, 0, 'Team', $own->id, null);
    $operation = match ($kind) {
        'owner' => fn () => app(SaveTeam::class)($owner, $team->id, 'Changed', $foreignMember->id, null),
        'assignment' => fn () => app(AssignTeamMember::class)($owner, $team->id, $foreignMember->id),
        'department' => fn () => app(SaveTeam::class)($owner, $team->id, 'Changed', $own->id, 999999),
        'inactive' => function () use ($owner, $org, $team) {
            $member = tenantMember($owner, $org, RoleName::Viewer);
            $member->forceFill(['status' => 'suspended'])->save();
            app(SaveTeam::class)($owner, $team->id, 'Changed', $member->id, null);
        },
    };
    expect($operation)->toThrow(ModelNotFoundException::class);
    expect($team->refresh()->name)->toBe('Team');
})->with(['owner', 'assignment', 'department', 'inactive']);

it('enforces structure permission boundaries for every role', function (string $role, bool $allowed) {
    [$owner,$org] = tenantWorkspace();
    $actor = $role === 'owner' ? $owner : tenantMember($owner, $org, RoleName::from($role))->user()->firstOrFail();
    tenantActor($actor, $org);
    $this->get(route('structure.index'))->assertOk();
    $create = fn () => app(SaveDepartment::class)($actor, 0, 'Department');
    if ($allowed) {
        expect($create()->name)->toBe('Department');
    } else {
        expect($create)->toThrow(AuthorizationException::class);
    }
})->with([['owner', true], ['admin', true], ['manager', true], ['support', false], ['viewer', false]]);

it('rolls back all bulk changes if any target is foreign privileged or the final owner', function (string $invalid) {
    [$owner,$org] = tenantWorkspace();
    $member = tenantMember($owner, $org, RoleName::Viewer);
    [, $foreign] = tenantWorkspace();
    tenantActor($owner, $org);
    $bad = $invalid === 'foreign' ? $foreign->memberships()->firstOrFail()->id : $org->memberships()->where('user_id', $owner->id)->firstOrFail()->id;
    try {
        app(BulkMembershipLifecycle::class)($owner, [$member->id, $bad], 'deactivated');
        $this->fail('Bulk mutation should fail.');
    } catch (ModelNotFoundException|ValidationException $e) {
    }
    expect($member->refresh()->status)->toBe(AccessStatus::Active);
})->with(['foreign', 'owner']);

it('scopes lifecycle and profile changes to one organization', function () {
    [$owner,$org] = tenantWorkspace();
    $user = User::factory()->create();
    $member = tenantMember($owner, $org, RoleName::Viewer, $user);
    [, $second] = tenantWorkspace($user, 'Other');
    tenantActor($owner, $org);
    app(UpdateMembership::class)($owner, $member->id, $member->assignedRole()->id, 'deactivated');
    tenantActor($user, $org);
    $this->get(route('dashboard'))->assertForbidden();
    tenantActor($user, $second);
    $this->get(route('dashboard'))->assertOk();
    tenantActor($owner, $org);
    app(UpdateMembership::class)($owner, $member->id, $member->assignedRole()->id, 'active');
    tenantActor($user, $org);
    $this->get(route('dashboard'))->assertOk();
});

it('supports password confirmed self deactivation and reactivation without restoring disabled memberships', function () {
    [$owner,$org] = tenantWorkspace();
    $member = tenantMember($owner, $org, RoleName::Viewer);
    $user = $member->user()->firstOrFail();
    tenantActor($user, $org);
    app(AccountLifecycle::class)($user, 'password', false);
    expect($user->refresh()->status)->toBe(AccessStatus::Deactivated);
    $this->get(route('dashboard'))->assertForbidden();
    app(AccountLifecycle::class)($user, 'password', true);
    tenantActor($user, $org);
    $this->get(route('dashboard'))->assertOk();
    expect(fn () => app(AccountLifecycle::class)($owner, 'password', false))->toThrow(ValidationException::class);
});

it('enforces composite foreign keys on structure assignments', function () {
    [$owner,$org] = tenantWorkspace();
    [, $foreign] = tenantWorkspace();
    tenantActor($owner, $org);
    $own = $org->memberships()->firstOrFail();
    $team = app(SaveTeam::class)($owner, 0, 'Team', $own->id, null);
    expect(fn () => DB::transaction(fn () => DB::table('team_memberships')->insert(['organization_id' => $org->id, 'team_id' => $team->id, 'membership_id' => $foreign->memberships()->firstOrFail()->id])))->toThrow(QueryException::class);
});
