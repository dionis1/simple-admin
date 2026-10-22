<?php

use App\Enums\RoleName;
use App\Livewire\Organizations\Show;
use App\Models\RoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('does not authorize foreign organization models even when the user owns both', function () {
    [$owner, $organization] = tenantWorkspace();
    [, $other] = tenantWorkspace($owner, 'Other workspace');
    tenantActor($owner, $organization);

    expect(Gate::forUser($owner)->allows('view', $other))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('update', $other))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('create', [RoleAssignment::class, tenantRole($other, RoleName::Owner)]))->toBeFalse();
});

it('denies unauthorized Livewire organization updates on the server', function () {
    [$owner, $organization] = tenantWorkspace();
    $viewer = tenantMember($owner, $organization, RoleName::Viewer)->user()->firstOrFail();
    tenantActor($viewer, $organization);
    Livewire::test(Show::class)->set('name', 'Forged')->call('save')->assertForbidden();
    expect($organization->refresh()->name)->toBe('Test workspace');
});

it('updates only the active organization and validates the name', function () {
    [$owner, $organization] = tenantWorkspace();
    [, $other] = tenantWorkspace($owner, 'Other workspace');
    tenantActor($owner, $organization);
    Livewire::test(Show::class)->set('name', '')->call('save')->assertHasErrors('name');
    Livewire::test(Show::class)->set('name', 'Renamed')->call('save')->assertHasNoErrors();
    expect($organization->refresh()->name)->toBe('Renamed')->and($other->refresh()->name)->toBe('Other workspace');
});
