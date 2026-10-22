<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

it('requires a verified account before showing the workspace', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $user = User::factory()->unverified()->create();
    $this->actingAs($user)->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));

    $user->markEmailAsVerified();
    $this->get(route('dashboard'))->assertOk()
        ->assertSee('Workspace overview')
        ->assertSee('Account settings');
});

it('does not expose legacy role or permission management', function (string $path) {
    $this->actingAs(User::factory()->create())->get($path)->assertNotFound();
})->with(['/admin', '/admin/users', '/admin/roles', '/admin/permissions']);

it('throttles repeated failed sign in attempts', function () {
    RateLimiter::clear('login');
    $email = 'throttle@example.com';

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('login.store'), ['email' => $email, 'password' => 'incorrect'])
            ->assertSessionHasErrors('email');
    }

    $this->postJson(route('login.store'), ['email' => $email, 'password' => 'incorrect'])
        ->assertTooManyRequests();
});

it('adds modern authentication without deleting legacy users or reset records', function () {
    $migration = require database_path('migrations/2026_10_22_000000_add_modern_authentication_tables.php');
    $migration->down();

    $password = Hash::make('legacy-password');
    $id = DB::table('users')->insertGetId([
        'name' => 'Legacy User',
        'email' => 'legacy@example.com',
        'password' => $password,
    ]);
    DB::table('password_resets')->insert([
        'email' => 'legacy@example.com', 'token' => 'legacy-token',
    ]);

    $migration->up();

    $user = User::findOrFail($id);
    expect($user->password)->toBe($password)
        ->and(Hash::check('legacy-password', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->toBeNull();
    $this->assertDatabaseHas('password_resets', ['token' => 'legacy-token']);
    $this->assertDatabaseCount('password_reset_tokens', 0);
});
