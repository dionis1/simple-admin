<?php

use App\Actions\Memberships\AddMembership;
use App\Actions\Memberships\RemoveMembership;
use App\Actions\Memberships\UpdateMembership;
use App\Actions\Organizations\DeleteAccount;
use App\Enums\AccessStatus;
use App\Enums\RoleName;
use App\Livewire\Memberships\Index;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('does not allow foreign membership ids to be edited or deleted', function (string $method) {
    [$owner, $organization] = tenantWorkspace();
    [, $foreign] = tenantWorkspace();
    $target = $foreign->memberships()->firstOrFail();
    tenantActor($owner, $organization);

    Livewire::test(Index::class)->call($method, $target->id)->assertNotFound();
    $this->assertDatabaseHas('memberships', ['id' => $target->id, 'status' => 'active']);
})->with(['edit', 'remove']);

it('rejects foreign roles and rolls back membership creation', function () {
    [$owner, $organization] = tenantWorkspace();
    [, $foreign] = tenantWorkspace();
    $user = User::factory()->create();
    tenantActor($owner, $organization);

    Livewire::test(Index::class)->set('email', $user->email)
        ->set('roleId', (string) tenantRole($foreign, RoleName::Owner)->id)->call('add')->assertNotFound();
    $this->assertDatabaseMissing('memberships', ['organization_id' => $organization->id, 'user_id' => $user->id]);
});

it('does not list foreign members', function () {
    [$owner, $organization] = tenantWorkspace();
    [$foreignOwner] = tenantWorkspace();
    tenantActor($owner, $organization);
    Livewire::test(Index::class)->assertSee($owner->email)->assertDontSee($foreignOwner->email);
});

it('allows admins to manage lower roles but never assign owner or admin', function (string $role, bool $allowed) {
    [$owner, $organization] = tenantWorkspace();
    $admin = tenantMember($owner, $organization, RoleName::Admin)->user()->firstOrFail();
    $newUser = User::factory()->create();
    tenantActor($admin, $organization);
    $operation = fn () => app(AddMembership::class)($admin, $newUser->email, tenantRole($organization, RoleName::from($role))->id);

    if ($allowed) {
        $membership = $operation();
        expect($membership->assignedRole()->name->value)->toBe($role);
    } else {
        expect($operation)->toThrow(AuthorizationException::class);
        $this->assertDatabaseMissing('memberships', ['user_id' => $newUser->id]);
    }
})->with([['owner', false], ['admin', false], ['manager', true], ['support', true], ['viewer', true]]);

it('blocks lower roles from creating updating or removing memberships', function (string $role) {
    [$owner, $organization] = tenantWorkspace();
    $user = tenantMember($owner, $organization, RoleName::from($role))->user()->firstOrFail();
    $target = tenantMember($owner, $organization, RoleName::Viewer);
    $newUser = User::factory()->create();
    tenantActor($user, $organization);
    $viewer = tenantRole($organization, RoleName::Viewer);

    expect(fn () => app(AddMembership::class)($user, $newUser->email, $viewer->id))->toThrow(AuthorizationException::class);
    expect(fn () => app(UpdateMembership::class)($user, $target->id, $viewer->id, 'suspended'))->toThrow(AuthorizationException::class);
    expect(fn () => app(RemoveMembership::class)($user, $target->id))->toThrow(AuthorizationException::class);
    expect($target->refresh()->status)->toBe(AccessStatus::Active);
})->with(['manager', 'support', 'viewer']);

it('prevents self promotion and changing a peer admin or owner', function (string $targetKind) {
    [$owner, $organization] = tenantWorkspace();
    $adminMembership = tenantMember($owner, $organization, RoleName::Admin);
    $admin = $adminMembership->user()->firstOrFail();
    $target = match ($targetKind) {
        'self' => $adminMembership,
        'peer' => tenantMember($owner, $organization, RoleName::Admin),
        'owner' => $organization->memberships()->where('user_id', $owner->id)->firstOrFail(),
    };
    tenantActor($admin, $organization);

    expect(fn () => app(UpdateMembership::class)($admin, $target->id, tenantRole($organization, RoleName::Owner)->id, 'active'))->toThrow(AuthorizationException::class);
    expect(fn () => app(RemoveMembership::class)($admin, $target->id))->toThrow(AuthorizationException::class);
})->with(['self', 'peer', 'owner']);

it('retains the last active owner when removal or suspension is attempted', function (string $operation) {
    [$owner, $organization] = tenantWorkspace();
    tenantActor($owner, $organization);
    $target = $organization->memberships()->firstOrFail();
    $change = $operation === 'remove'
        ? fn () => app(RemoveMembership::class)($owner, $target->id)
        : fn () => app(UpdateMembership::class)($owner, $target->id, tenantRole($organization, RoleName::Owner)->id, 'suspended');

    expect($change)->toThrow(ValidationException::class);
    expect($target->refresh()->status)->toBe(AccessStatus::Active);
})->with(['remove', 'suspend']);

it('does not count suspended owners when protecting the final active owner', function () {
    [$owner, $organization] = tenantWorkspace();
    $other = tenantMember($owner, $organization, RoleName::Owner);
    $other->forceFill(['status' => AccessStatus::Suspended])->save();
    tenantActor($owner, $organization);
    $target = $organization->memberships()->where('user_id', $owner->id)->firstOrFail();
    expect(fn () => app(RemoveMembership::class)($owner, $target->id))->toThrow(ValidationException::class);
});

it('permits ownership transfer and demotion only by another active owner', function () {
    [$owner, $organization] = tenantWorkspace();
    $second = tenantMember($owner, $organization, RoleName::Owner);
    $nextOwner = $second->user()->firstOrFail();
    $original = $organization->memberships()->where('user_id', $owner->id)->firstOrFail();
    tenantActor($nextOwner, $organization);
    app(UpdateMembership::class)($nextOwner, $original->id, tenantRole($organization, RoleName::Viewer)->id, 'active');
    expect($original->assignedRole()->name)->toBe(RoleName::Viewer)
        ->and($second->assignedRole()->name)->toBe(RoleName::Owner);
});

it('cannot bypass ownership protection through account deletion', function () {
    [$owner, $organization] = tenantWorkspace();
    tenantActor($owner, $organization);
    expect(fn () => app(DeleteAccount::class)($owner))->toThrow(ValidationException::class);
    Livewire::test('pages::settings.delete-user-modal')->set('password', 'password')->call('deleteUser')->assertHasErrors('password');
    $this->assertDatabaseHas('users', ['id' => $owner->id]);
});

it('revalidates permissions on an existing Livewire component after revocation', function () {
    [$owner, $organization] = tenantWorkspace();
    $adminMembership = tenantMember($owner, $organization, RoleName::Admin);
    $admin = $adminMembership->user()->firstOrFail();
    $target = tenantMember($owner, $organization, RoleName::Viewer);
    tenantActor($admin, $organization);
    $component = Livewire::test(Index::class);
    $adminMembership->forceFill(['status' => AccessStatus::Suspended])->save();
    $component->call('remove', $target->id)->assertForbidden();
    $this->assertDatabaseHas('memberships', ['id' => $target->id]);
});

it('scopes edits again after the active organization changes in another tab', function () {
    [$owner, $organization] = tenantWorkspace();
    [, $second] = tenantWorkspace($owner, 'Second');
    $target = tenantMember($owner, $organization, RoleName::Viewer);
    tenantActor($owner, $organization);
    $component = Livewire::test(Index::class)->call('edit', $target->id);
    session()->put(TenantContext::SESSION_KEY, $second->id);
    $component->call('save')->assertNotFound();
    expect($target->refresh()->status)->toBe(AccessStatus::Active);
});

it('validates duplicate unverified and missing membership accounts without partial writes', function (string $condition) {
    [$owner, $organization] = tenantWorkspace();
    $email = match ($condition) {
        'duplicate' => $owner->email,
        'unverified' => User::factory()->unverified()->create()->email,
        'missing' => 'missing@example.test',
        'invalid' => 'not-email',
    };
    tenantActor($owner, $organization);
    Livewire::test(Index::class)->set('email', $email)->set('roleId', (string) tenantRole($organization, RoleName::Viewer)->id)
        ->call('add')->assertHasErrors('email');
    expect($organization->memberships()->count())->toBe(1);
})->with(['duplicate', 'unverified', 'missing', 'invalid']);

it('allows an admin to suspend reactivate and remove a lower membership', function () {
    [$owner, $organization] = tenantWorkspace();
    $admin = tenantMember($owner, $organization, RoleName::Admin)->user()->firstOrFail();
    $target = tenantMember($owner, $organization, RoleName::Viewer);
    tenantActor($admin, $organization);
    $role = tenantRole($organization, RoleName::Support);
    app(UpdateMembership::class)($admin, $target->id, $role->id, 'suspended');
    expect($target->refresh()->status)->toBe(AccessStatus::Suspended)->and($target->assignedRole()->name)->toBe(RoleName::Support);
    app(UpdateMembership::class)($admin, $target->id, $role->id, 'active');
    app(RemoveMembership::class)($admin, $target->id);
    $this->assertDatabaseMissing('memberships', ['id' => $target->id]);
    $this->assertDatabaseMissing('role_assignments', ['membership_id' => $target->id]);
});

it('rejects another organizations role during an existing membership edit', function () {
    [$owner, $organization] = tenantWorkspace();
    [, $foreign] = tenantWorkspace();
    $target = tenantMember($owner, $organization, RoleName::Viewer);
    tenantActor($owner, $organization);
    Livewire::test(Index::class)->call('edit', $target->id)
        ->set('editRoleId', (string) tenantRole($foreign, RoleName::Owner)->id)->call('save')->assertNotFound();
    expect($target->assignedRole()->name)->toBe(RoleName::Viewer);
});

it('rechecks a role downgrade on an already mounted component', function () {
    [$owner, $organization] = tenantWorkspace();
    $adminMembership = tenantMember($owner, $organization, RoleName::Admin);
    $admin = $adminMembership->user()->firstOrFail();
    $target = tenantMember($owner, $organization, RoleName::Viewer);
    tenantActor($admin, $organization);
    $component = Livewire::test(Index::class);
    app(UpdateMembership::class)($owner, $adminMembership->id, tenantRole($organization, RoleName::Viewer)->id, 'active');
    $component->call('remove', $target->id)->assertForbidden();
    $this->assertDatabaseHas('memberships', ['id' => $target->id]);
});

it('supports validated Livewire membership creation and edits', function () {
    [$owner, $organization] = tenantWorkspace();
    $user = User::factory()->create();
    tenantActor($owner, $organization);
    Livewire::test(Index::class)->set('email', $user->email)->set('roleId', 'not-an-id')
        ->call('add')->assertHasErrors('roleId');
    Livewire::test(Index::class)->set('email', $user->email)->set('roleId', (string) tenantRole($organization, RoleName::Viewer)->id)
        ->call('add')->assertHasNoErrors()->assertSee($user->email);
    $target = $organization->memberships()->where('user_id', $user->id)->firstOrFail();
    Livewire::test(Index::class)->call('edit', $target->id)
        ->set('editRoleId', (string) tenantRole($organization, RoleName::Manager)->id)
        ->set('editStatus', 'suspended')->call('save')->assertHasNoErrors();
    expect($target->refresh()->status)->toBe(AccessStatus::Suspended)
        ->and($target->assignedRole()->name)->toBe(RoleName::Manager);
});

it('rejects invalid membership states without changing the assignment', function () {
    [$owner, $organization] = tenantWorkspace();
    $target = tenantMember($owner, $organization, RoleName::Viewer);
    tenantActor($owner, $organization);
    Livewire::test(Index::class)->call('edit', $target->id)
        ->set('editRoleId', (string) tenantRole($organization, RoleName::Manager)->id)
        ->set('editStatus', 'invalid')->call('save')->assertHasErrors('status');
    expect($target->refresh()->status)->toBe(AccessStatus::Active)
        ->and($target->assignedRole()->name)->toBe(RoleName::Viewer);
});

it('lets an owner leave only after another active owner exists', function () {
    [$owner, $organization] = tenantWorkspace();
    $second = tenantMember($owner, $organization, RoleName::Owner);
    $ownMembership = $organization->memberships()->where('user_id', $owner->id)->firstOrFail();
    tenantActor($owner, $organization);
    Livewire::test(Index::class)->call('remove', $ownMembership->id)->assertRedirect(route('organizations.index'));
    expect(app(TenantContext::class)->current($owner))->toBeNull();
    $this->assertDatabaseHas('memberships', ['id' => $second->id, 'status' => 'active']);
});
