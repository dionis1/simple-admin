<?php

use App\Actions\Invitations\AcceptInvitation;
use App\Actions\Invitations\IssueInvitation;
use App\Actions\Memberships\UpdateMembership;
use App\Enums\AccessStatus;
use App\Enums\RoleName;
use App\Livewire\Invitations\Accept;
use App\Livewire\Invitations\Index;
use App\Models\User;
use App\Notifications\OrganizationInvitation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function identityInvitation(User $owner, $org, string $email, RoleName $role = RoleName::Viewer): array
{
    Notification::fake();
    tenantActor($owner, $org);
    $invitation = app(IssueInvitation::class)($owner, $email, tenantRole($org, $role)->id);
    $token = '';
    Notification::assertSentOnDemand(OrganizationInvitation::class, function ($notice) use (&$token) {
        $token = $notice->token;

        return true;
    });

    return [$invitation, $token];
}

it('stores hashed invitation tokens and accepts them once for the verified recipient', function () {
    [$owner,$org] = tenantWorkspace();
    $recipient = User::factory()->create();
    [$invite,$token] = identityInvitation($owner, $org, $recipient->email);
    expect($invite->getAttribute('token_hash'))->toBe(hash('sha256', $token))->not->toBe($token)
        ->and($invite->expires_at->isFuture())->toBeTrue()
        ->and($invite->toArray())->not->toHaveKey('token_hash');
    $member = app(AcceptInvitation::class)($recipient, $token);
    expect($member->organization_id)->toBe($org->id)->and($member->assignedRole()->name)->toBe(RoleName::Viewer);
    expect(fn () => app(AcceptInvitation::class)($recipient, $token))->toThrow(HttpException::class);
    expect($org->memberships()->where('user_id', $recipient->id)->count())->toBe(1);
});

it('rejects expired revoked mismatched inactive and unverified acceptance', function (string $condition) {
    [$owner,$org] = tenantWorkspace();
    $recipient = User::factory()->create();
    [$invite,$token] = identityInvitation($owner, $org, $recipient->email);
    match ($condition) {
        'expired' => $invite->forceFill(['expires_at' => now()->subSecond()])->save(),
        'revoked' => $invite->forceFill(['revoked_at' => now()])->save(),
        'mismatch' => $recipient->forceFill(['email' => 'different@example.test'])->save(),
        'suspended' => $recipient->forceFill(['status' => AccessStatus::Suspended])->save(),
        'deactivated' => $recipient->forceFill(['status' => AccessStatus::Deactivated])->save(),
        'unverified' => $recipient->forceFill(['email_verified_at' => null])->save(),
    };
    expect(fn () => app(AcceptInvitation::class)($recipient, $token))->toThrow(HttpException::class);
    $this->assertDatabaseMissing('memberships', ['organization_id' => $org->id, 'user_id' => $recipient->id]);
})->with(['expired', 'revoked', 'mismatch', 'suspended', 'deactivated', 'unverified']);

it('rotates invitation links and never revives a disabled existing membership', function () {
    [$owner,$org] = tenantWorkspace();
    $recipient = User::factory()->create();
    [$first,$old] = identityInvitation($owner, $org, $recipient->email);
    [$second,$new] = identityInvitation($owner, $org, $recipient->email);
    expect($first->refresh()->revoked_at)->not->toBeNull()->and($old)->not->toBe($new);
    expect(fn () => app(AcceptInvitation::class)($recipient, $old))->toThrow(HttpException::class);
    $member = tenantMember($owner, $org, RoleName::Viewer, $recipient);
    app(UpdateMembership::class)($owner, $member->id, $member->assignedRole()->id, 'deactivated');
    expect(fn () => app(AcceptInvitation::class)($recipient, $new))->toThrow(ValidationException::class);
    expect($second->refresh()->accepted_at)->toBeNull();
});

it('rechecks the inviters current authority at acceptance', function () {
    [$owner,$org] = tenantWorkspace();
    $adminMembership = tenantMember($owner, $org, RoleName::Admin);
    $admin = $adminMembership->user()->firstOrFail();
    $recipient = User::factory()->create();
    [$invite,$token] = identityInvitation($admin, $org, $recipient->email);
    app(UpdateMembership::class)($owner, $adminMembership->id, tenantRole($org, RoleName::Viewer)->id, 'active');
    expect(fn () => app(AcceptInvitation::class)($recipient, $token))->toThrow(HttpException::class);
    expect($invite->refresh()->accepted_at)->toBeNull();
});

it('prevents invitation role escalation and foreign role ids', function (string $role) {
    [$owner,$org] = tenantWorkspace();
    $admin = tenantMember($owner, $org, RoleName::Admin)->user()->firstOrFail();
    tenantActor($admin, $org);
    expect(fn () => app(IssueInvitation::class)($admin, 'new@example.test', tenantRole($org, RoleName::from($role))->id))->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('invitations', 0);
})->with(['owner', 'admin']);

it('denies foreign invitation management and unauthorized members', function () {
    [$owner,$org] = tenantWorkspace();
    [$other,$foreign] = tenantWorkspace();
    [$invite] = identityInvitation($other, $foreign, 'new@example.test');
    tenantActor($owner, $org);
    Livewire::test(Index::class)->call('revoke', $invite->id)->assertNotFound();
    $viewer = tenantMember($owner, $org, RoleName::Viewer)->user()->firstOrFail();
    tenantActor($viewer, $org);
    $this->get(route('invitations.index'))->assertForbidden();
});

it('accepts through Livewire and never exposes another recipients invitation', function () {
    [$owner,$org] = tenantWorkspace();
    $recipient = User::factory()->create();
    [$invite,$token] = identityInvitation($owner, $org, $recipient->email);
    $this->actingAs($owner)->get(route('invitations.accept', ['token' => $token]))->assertForbidden();
    $this->actingAs($recipient);
    Livewire::test(Accept::class, ['token' => $token])->assertSee($org->name)->call('accept')->assertRedirect(route('dashboard'));
    expect($invite->refresh()->accepted_at)->not->toBeNull();
});

it('queues encrypted notifications without storing plaintext tokens in the database', function () {
    $notice = new OrganizationInvitation('test-secret', 'Example');
    expect($notice)->toBeInstanceOf(ShouldQueue::class)->toBeInstanceOf(ShouldBeEncrypted::class);
    $job = new SendQueuedNotifications([], $notice);
    expect($job->shouldBeEncrypted)->toBeTrue();
});
