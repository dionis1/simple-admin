<?php

use App\Actions\Security\IssueApiToken;
use App\Actions\Security\RevokeSession;
use App\Livewire\Security\Identity;
use App\Models\User;
use App\Notifications\SecurityNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

function identityMfaUser(): User
{
    $user = User::factory()->create();
    $secret = (new Google2FA)->generateSecretKey();
    $user->forceFill(['two_factor_secret' => encrypt($secret), 'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-one', 'recovery-code-two'])), 'two_factor_confirmed_at' => now()])->save();

    return $user;
}

it('enables confirms regenerates and disables native TOTP through the security component', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    Livewire::test(Identity::class)->set('currentPassword', 'wrong')->call('mfa', 'enable')->assertHasErrors('currentPassword');
    $component = Livewire::test(Identity::class)->set('currentPassword', 'password')->call('mfa', 'enable')->assertHasNoErrors();
    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()->and($user->two_factor_confirmed_at)->toBeNull();
    expect($user->two_factor_secret)->not->toBe(decrypt($user->two_factor_secret));
    $component->set('currentPassword', 'password')->set('code', 'invalid')->call('mfa', 'confirm')->assertHasErrors('code');
    $otp = (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret));
    $component->set('currentPassword', 'password')->set('code', $otp)->call('mfa', 'confirm')->assertHasNoErrors();
    expect($user->refresh()->two_factor_confirmed_at)->not->toBeNull();
    $old = $user->recoveryCodes();
    $component->set('currentPassword', 'password')->call('mfa', 'regenerate')->assertHasNoErrors();
    expect($user->refresh()->recoveryCodes())->not->toBe($old)->toHaveCount(8);
    $component->set('currentPassword', 'password')->call('mfa', 'disable')->assertHasNoErrors();
    expect($user->refresh()->two_factor_secret)->toBeNull()->and($user->two_factor_recovery_codes)->toBeNull();
    Notification::assertSentTo($user, SecurityNotice::class);
});

it('requires TOTP before login and rejects invalid codes', function () {
    $user = identityMfaUser();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
    $this->get(route('two-factor.login'))->assertOk();
    $this->postJson(route('two-factor.login.store'), ['code' => 'invalid'])->assertUnprocessable();
    $this->assertGuest();
    $otp = (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret));
    $this->post(route('two-factor.login.store'), ['code' => $otp])->assertRedirect('/organizations');
    $this->assertAuthenticatedAs($user);
});

it('consumes recovery codes only once', function () {
    $user = identityMfaUser();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-one'])->assertRedirect('/organizations');
    expect($user->refresh()->recoveryCodes())->not->toContain('recovery-code-one');
    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->postJson(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-one'])->assertUnprocessable();
    $this->assertGuest();
});

it('rechecks global suspension between the password and MFA steps', function () {
    $user = identityMfaUser();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $user->forceFill(['status' => 'suspended'])->save();
    $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-one'])->assertForbidden();
    $this->assertGuest();
});

it('protects MFA secrets behind password confirmation and excludes them from serialized users', function () {
    $user = identityMfaUser();
    $this->actingAs($user);
    $this->get(route('identity.security'))->assertRedirect(route('password.confirm'));
    $this->get('/user/two-factor-secret-key')->assertRedirect(route('password.confirm'));
    expect($user->toArray())->not->toHaveKeys(['two_factor_secret', 'two_factor_recovery_codes', 'password']);
    Livewire::test(Identity::class)->set('currentPassword', 'wrong')->call('mfa', 'disable')->assertHasErrors('currentPassword');
    expect($user->refresh()->two_factor_secret)->not->toBeNull();
});

it('lists and revokes only the actors database sessions with password confirmation', function () {
    Notification::fake();
    $user = User::factory()->create();
    $other = User::factory()->create();
    foreach (['own-session' => $user, 'foreign-session' => $other] as $id => $owner) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time(), 'user_agent' => $id]);
    }
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    Livewire::test(Identity::class)->assertSee('own-session')->assertDontSee('foreign-session')
        ->set('currentPassword', 'password')->call('revokeSession', 'foreign-session')->assertNotFound();
    expect(fn () => app(RevokeSession::class)($user, 'wrong', 'own-session'))->toThrow(ValidationException::class);
    $remember = $user->remember_token;
    app(RevokeSession::class)($user, 'password', 'own-session');
    $this->assertDatabaseMissing('sessions', ['id' => 'own-session']);
    $this->assertDatabaseHas('sessions', ['id' => 'foreign-session']);
    expect($user->refresh()->remember_token)->not->toBe($remember);
});

it('revokes tokens and other sessions when the password changes', function () {
    [$owner,$org] = tenantWorkspace();
    tenantActor($owner, $org);
    $plain = app(IssueApiToken::class)($owner, 'password', 'CLI', ['memberships.view'], 30);
    DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);
    $owner->forceFill(['password' => 'changed-password'])->save();
    $this->assertDatabaseCount('personal_access_tokens', 0);
    $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
    expect(Hash::check('changed-password', $owner->password))->toBeTrue();
});

it('redirects deactivated users to account recovery and blocks suspended login', function (string $status) {
    $user = User::factory()->create();
    $user->forceFill(['status' => $status])->save();
    $response = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    if ($status === 'deactivated') {
        $response->assertRedirect(route('identity.security'));
        $this->get(route('organizations.index'))->assertForbidden();
    } else {
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
})->with(['deactivated', 'suspended']);

it('revokes a browser session through an opaque reference without exposing the stored session id', function () {
    $user = User::factory()->create();
    DB::table('sessions')->insert(['id' => 'private-session-identifier', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time(), 'user_agent' => 'Other browser']);
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    $component = Livewire::test(Identity::class)->assertDontSee('private-session-identifier');
    preg_match("/revokeSession\\('([a-f0-9]{64})'\\)/", $component->html(), $matches);
    expect($matches[1] ?? null)->toBeString();
    $component->set('currentPassword', 'password')->call('revokeSession', $matches[1])->assertHasNoErrors();
    $this->assertDatabaseMissing('sessions', ['id' => 'private-session-identifier']);
});

it('reuses password confirmed native MFA endpoints and revokes older credentials on confirmation', function () {
    [$owner,$org] = tenantWorkspace();
    tenantActor($owner, $org);
    app(IssueApiToken::class)($owner, 'password', 'Before MFA', ['memberships.view'], 30);
    DB::table('sessions')->insert(['id' => 'older-browser', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);
    $this->withSession(['auth.password_confirmed_at' => time()]);
    $this->postJson('/user/two-factor-authentication')->assertSuccessful();
    $owner->refresh();
    $code = (new Google2FA)->getCurrentOtp(decrypt($owner->two_factor_secret));
    $this->postJson('/user/confirmed-two-factor-authentication', ['code' => $code])->assertSuccessful();
    expect($owner->refresh()->two_factor_confirmed_at)->not->toBeNull();
    $this->assertDatabaseCount('personal_access_tokens', 0);
    $this->assertDatabaseMissing('sessions', ['id' => 'older-browser']);
});
