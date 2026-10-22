<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('sends verification to newly registered accounts without trusting their email', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'New User', 'email' => 'new@example.com',
        'password' => 'test-password', 'password_confirmation' => 'test-password',
    ])->assertSessionHasNoErrors();

    $user = User::where('email', 'new@example.com')->firstOrFail();
    expect($user->hasVerifiedEmail())->toBeFalse()
        ->and(Hash::check('test-password', $user->password))->toBeTrue();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejects invalid registration without creating a user', function () {
    $this->post(route('register.store'), [
        'name' => '', 'email' => 'invalid',
        'password' => 'short', 'password_confirmation' => 'different',
    ])->assertSessionHasErrors(['name', 'email', 'password']);

    $this->assertDatabaseCount('users', 0);
});

it('rejects expired verification links', function () {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
        'id' => $user->id, 'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->get($url)->assertForbidden();
    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('changes the password using a valid reset token and consumes that token', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'token' => $token, 'email' => $user->email,
        'password' => 'changed-password', 'password_confirmation' => 'changed-password',
    ])->assertSessionHasNoErrors()->assertRedirect(route('login', absolute: false));

    expect(Hash::check('changed-password', $user->refresh()->password))->toBeTrue();
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
});

it('rejects an invalid reset token without changing the password', function () {
    $user = User::factory()->create();
    $original = $user->password;

    $this->post(route('password.update'), [
        'token' => 'invalid-token', 'email' => $user->email,
        'password' => 'changed-password', 'password_confirmation' => 'changed-password',
    ])->assertSessionHasErrors('email');

    expect($user->refresh()->password)->toBe($original);
});

it('validates profile changes and requires re-verification after changing email', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('email', 'not-an-email')->call('updateProfileInformation')
        ->assertHasErrors(['email']);
    expect($user->refresh()->email_verified_at)->not->toBeNull();

    Livewire::test('pages::settings.profile')
        ->set('email', 'changed@example.com')->call('updateProfileInformation')
        ->assertHasNoErrors();
    expect($user->refresh()->email)->toBe('changed@example.com')
        ->and($user->hasVerifiedEmail())->toBeFalse();
    $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
});
