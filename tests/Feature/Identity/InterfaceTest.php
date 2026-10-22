<?php

use App\Enums\RoleName;
use App\Livewire\Memberships\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('searches filters sorts paginates and bulk updates authorized organization memberships', function () {
    [$owner,$org] = tenantWorkspace();
    $member = tenantMember($owner, $org, RoleName::Viewer, User::factory()->create(['name' => 'Needle Member']));
    tenantActor($owner, $org);
    Livewire::test(Index::class)->set('search', 'Needle')->set('sort', 'oldest')->assertSee('Needle Member')->assertDontSee($owner->email)
        ->set('selected', [$member->id])->set('bulkStatus', 'deactivated')->call('bulk')->assertHasNoErrors()
        ->set('statusFilter', 'deactivated')->assertSee('Needle Member');
    expect($member->refresh()->status->value)->toBe('deactivated');
});

it('rejects manipulated sorting and unauthorized bulk operations', function () {
    [$owner,$org] = tenantWorkspace();
    $member = tenantMember($owner, $org, RoleName::Viewer);
    $viewer = $member->user()->firstOrFail();
    tenantActor($owner, $org);
    Livewire::test(Index::class)->set('sort', 'id; DROP TABLE users')->assertStatus(422);
    tenantActor($viewer, $org);
    Livewire::test(Index::class)->assertForbidden();
});

it('validates locales and renders German and English management and security interfaces', function () {
    [$owner,$org] = tenantWorkspace();
    tenantActor($owner, $org);
    $this->post(route('locale.update'), ['locale' => 'de'])->assertRedirect();
    $this->get(route('memberships.index'))->assertOk()->assertSee('Mitgliedschaften');
    $this->withSession(['auth.password_confirmed_at' => time()])->get(route('identity.security'))->assertOk()->assertSee('Kontosicherheit');
    $this->post(route('locale.update'), ['locale' => 'invalid'])->assertSessionHasErrors('locale');
    $this->post(route('locale.update'), ['locale' => 'en']);
    $this->get(route('memberships.index'))->assertOk()->assertSee('Memberships');
});
