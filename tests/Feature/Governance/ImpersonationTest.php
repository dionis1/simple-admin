<?php

use App\Actions\Governance\DecideApproval;
use App\Actions\Governance\RequestApproval;
use App\Actions\Governance\SupportAccess;
use App\Actions\Memberships\RemoveMembership;
use App\Actions\Security\RevokeCredentials;
use App\Enums\RoleName;
use App\Livewire\Governance\Approvals;
use App\Models\AuditEvent;
use App\Models\SupportGrant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function supportFixture(): array
{
    [$owner, $org] = tenantWorkspace();
    $support = tenantMember($owner, $org, RoleName::Support)->user()->firstOrFail();
    $target = tenantMember($owner, $org, RoleName::Viewer);
    tenantActor($support, $org);
    $request = app(RequestApproval::class)($support, 'support_access', 'Investigate a reported dashboard problem.', $target->id);
    tenantActor($owner, $org);
    app(DecideApproval::class)($owner, $request->id, true, 'password');
    $grant = SupportGrant::where('approval_request_id', $request->id)->firstOrFail();
    $secret = (new Google2FA)->generateSecretKey();
    $support->forceFill(['two_factor_secret' => encrypt($secret), 'two_factor_confirmed_at' => now()])->save();
    tenantActor($support, $org);

    return [$owner, $org, $support->fresh(), $target, $grant, $secret];
}

it('requires independent approval and real password plus fresh TOTP before read-only support starts', function () {
    [$owner, $org, $support, $target, $grant, $secret] = supportFixture();
    expect(fn () => app(SupportAccess::class)->start($support, $grant->id, 'wrong', '123456'))->toThrow(ValidationException::class);
    expect(fn () => app(SupportAccess::class)->start($support, $grant->id, 'password', 'not-code'))->toThrow(HttpException::class);
    app(SupportAccess::class)->start($support, $grant->id, 'password', (new Google2FA)->getCurrentOtp($secret));
    $this->withCookie(config('session.cookie'), session()->getId());
    expect(session(SupportAccess::SESSION_KEY))->toBe($grant->id);
    $this->get(route('dashboard'))->assertOk()->assertSee('Read-only support access is active.')->assertSee('Viewing as user ID');
    expect(auth()->id())->toBe($support->id)
        ->and(AuditEvent::where('event', 'support.viewed')->latest('id')->firstOrFail()->getAttribute('subject_user_id'))->toBe($target->user_id);
    $this->get(route('organization.show'))->assertOk();
    $this->get(route('structure.index'))->assertOk();
    $this->get(route('profile.edit'))->assertForbidden();
    $this->get(route('identity.security'))->assertForbidden();
    $this->get(route('audit.export'))->assertForbidden();
    $this->withHeader('X-Livewire', 'true')->post(app('livewire')->getUpdateUri(), [])->assertForbidden();
    $this->post(route('locale.update'), ['locale' => 'de'])->assertForbidden();
    $this->post(route('support.stop'))->assertRedirect(route('dashboard'));
    expect(session()->has(SupportAccess::SESSION_KEY))->toBeFalse()->and($grant->refresh()->ended_at)->not->toBeNull();
    expect(fn () => app(SupportAccess::class)->start($support, $grant->id, 'password', (new Google2FA)->getCurrentOtp($secret)))->toThrow(HttpException::class);
});

it('fails closed when support expires loses permissions or is bound to a different session', function (string $mode) {
    [$owner, $org, $support, $target, $grant, $secret] = supportFixture();
    app(SupportAccess::class)->start($support, $grant->id, 'password', (new Google2FA)->getCurrentOtp($secret));
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->get(route('dashboard'))->assertOk();
    match ($mode) {
        'expired' => $grant->forceFill(['expires_at' => now()->subSecond()])->save(),
        'session' => session()->regenerate(),
        'target' => $target->forceFill(['status' => 'suspended'])->save(),
        'operator' => $support->memberships()->where('organization_id', $org->id)->update(['status' => 'suspended']),
        'reviewer' => $owner->memberships()->where('organization_id', $org->id)->update(['status' => 'suspended']),
        'credentials' => app(RevokeCredentials::class)($support),
        'privileged_target' => $target->roleAssignment()->update(['role_id' => tenantRole($org, RoleName::Admin)->id]),
    };
    if ($mode === 'session') {
        $this->withCookie(config('session.cookie'), session()->getId());
    }
    $this->get(route('dashboard'))->assertForbidden();
    expect(session()->has(SupportAccess::SESSION_KEY))->toBeFalse()->and($grant->refresh()->ended_at)->not->toBeNull();
})->with(['expired', 'session', 'target', 'operator', 'reviewer', 'privileged_target', 'credentials']);

it('cannot request support for self privileged or foreign targets or start another operators grant', function () {
    [$owner, $org, $support, $target, $grant, $secret] = supportFixture();
    [$foreign, $other] = tenantWorkspace();
    tenantActor($support, $org);
    foreach ([$org->memberships()->where('user_id', $support->id)->firstOrFail()->id, $org->memberships()->where('user_id', $owner->id)->firstOrFail()->id] as $id) {
        expect(fn () => app(RequestApproval::class)($support, 'support_access', 'Attempt an unauthorized support session.', $id))->toThrow(HttpException::class);
    }
    expect(fn () => app(RequestApproval::class)($support, 'support_access', 'Attempt cross tenant support session.', $other->memberships()->firstOrFail()->id))->toThrow(ModelNotFoundException::class);
    tenantActor($owner, $org);
    expect(fn () => app(SupportAccess::class)->start($owner, $grant->id, 'password', '123456'))->toThrow(ModelNotFoundException::class);
});

it('does not accept recovery codes or unconfirmed MFA as support step up', function () {
    [$owner, $org, $support, $target, $grant, $secret] = supportFixture();
    $support->forceFill(['two_factor_confirmed_at' => null])->save();
    expect(fn () => app(SupportAccess::class)->start($support, $grant->id, 'password', (new Google2FA)->getCurrentOtp($secret)))->toThrow(HttpException::class);
    expect($grant->refresh()->started_at)->toBeNull();
});

it('starts support through Livewire and clears step up fields before redirecting', function () {
    [$owner, $org, $support, $target, $grant, $secret] = supportFixture();
    Livewire::test(Approvals::class)
        ->set('currentPassword', 'password')->set('totp', (new Google2FA)->getCurrentOtp($secret))
        ->call('start', $grant->id)->assertRedirect(route('dashboard'))
        ->assertSet('currentPassword', '')->assertSet('totp', '');
    expect($grant->refresh()->started_at)->not->toBeNull();
});

it('does not let historical support grants prevent membership removal and ends the view after removal', function () {
    [$owner, $org, $support, $target, $grant, $secret] = supportFixture();
    app(SupportAccess::class)->start($support, $grant->id, 'password', (new Google2FA)->getCurrentOtp($secret));
    $cookie = session()->getId();
    // Simulate an independent owner request; do not retain the support session's marker.
    session()->forget(SupportAccess::SESSION_KEY);
    tenantActor($owner, $org);
    app(RemoveMembership::class)($owner, $target->id);
    tenantActor($support, $org);
    session()->put(SupportAccess::SESSION_KEY, $grant->id);
    $this->withCookie(config('session.cookie'), $cookie)->get(route('dashboard'))->assertForbidden();
    expect(SupportGrant::find($grant->id))->toBeNull()->and(session()->has(SupportAccess::SESSION_KEY))->toBeFalse();
});
