<?php

use App\Actions\Security\IssueApiToken;
use App\Actions\Security\RevokeApiToken;
use App\Enums\RoleName;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function identityApi(string $method, string $url, string $token, array $data = [])
{
    Auth::forgetGuards();

    return test()->withToken($token)->json($method, $url, $data);
}

it('issues hashed expiring tokens bound to one organization and returns permitted resources', function () {
    [$owner,$org] = tenantWorkspace();
    [, $foreign] = tenantWorkspace();
    tenantActor($owner, $org);
    $plain = app(IssueApiToken::class)($owner, 'password', 'Read only', ['memberships.view'], 2);
    $record = PersonalAccessToken::firstOrFail();
    expect($record->organization_id)->toBe($org->id)->and($record->getAttribute('token'))->toBe(hash('sha256', explode('|', $plain)[1]))
        ->and($record->expires_at->isFuture())->toBeTrue();
    identityApi('GET', '/api/v1/memberships', $plain)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.user.email', $owner->email);
    identityApi('GET', '/api/v1/memberships/'.$foreign->memberships()->firstOrFail()->id, $plain)->assertNotFound();
});

it('requires both token ability and current resource permissions', function () {
    [$owner,$org] = tenantWorkspace();
    $member = tenantMember($owner, $org, RoleName::Viewer);
    tenantActor($owner, $org);
    $token = app(IssueApiToken::class)($owner, 'password', 'View', ['memberships.view'], 30);
    identityApi('PATCH', '/api/v1/memberships/'.$member->id, $token, ['role_id' => $member->assignedRole()->id, 'status' => 'suspended'])->assertForbidden();
    $own = $org->memberships()->where('user_id', $owner->id)->firstOrFail();
    DB::table('role_assignments')->where('membership_id', $own->id)->update(['role_id' => tenantRole($org, RoleName::Viewer)->id]);
    identityApi('GET', '/api/v1/memberships', $token)->assertForbidden();
});

it('cannot create wildcard unauthorized or unbounded tokens', function (string $case) {
    [$owner,$org] = tenantWorkspace();
    $viewer = tenantMember($owner, $org, RoleName::Viewer)->user()->firstOrFail();
    $actor = $case === 'unauthorized' ? $viewer : $owner;
    tenantActor($actor, $org);
    $abilities = match ($case) {
        'wildcard' => ['*'], 'unknown' => ['system.admin'], default => ['memberships.view']
    };
    $days = $case === 'expiry' ? 91 : 30;
    $operation = fn () => app(IssueApiToken::class)($actor, $case === 'password' ? 'wrong' : 'password', 'Invalid', $abilities, $days);
    if ($case === 'unauthorized') {
        expect($operation)->toThrow(HttpException::class);
    } else {
        expect($operation)->toThrow(ValidationException::class);
    }
    $this->assertDatabaseCount('personal_access_tokens', 0);
})->with(['wildcard', 'unknown', 'unauthorized', 'expiry', 'password']);

it('rejects expired revoked and suspended memberships and ignores browser tenant ids', function (string $case) {
    [$owner,$org] = tenantWorkspace();
    [, $foreign] = tenantWorkspace();
    tenantActor($owner, $org);
    $token = app(IssueApiToken::class)($owner, 'password', 'Read', ['memberships.view'], 30);
    $record = PersonalAccessToken::firstOrFail();
    match ($case) {
        'expired' => $record->forceFill(['expires_at' => now()->subSecond()])->save(),
        'revoked' => $record->delete(),
        'membership' => DB::table('memberships')->where('organization_id', $org->id)->update(['status' => 'suspended']),
        'account' => DB::table('users')->where('id', $owner->id)->update(['status' => 'suspended']),
    };
    $response = identityApi('GET', '/api/v1/memberships?organization_id='.$foreign->id, $token);
    $response->assertStatus(in_array($case, ['expired', 'revoked'], true) ? 401 : 403);
})->with(['expired', 'revoked', 'membership', 'account']);

it('checks membership hierarchy and role assignment scopes on API updates', function () {
    [$owner,$org] = tenantWorkspace();
    $target = tenantMember($owner, $org, RoleName::Viewer);
    tenantActor($owner, $org);
    $token = app(IssueApiToken::class)($owner, 'password', 'Status', ['memberships.update'], 30);
    identityApi('PATCH', '/api/v1/memberships/'.$target->id, $token, ['role_id' => tenantRole($org, RoleName::Admin)->id, 'status' => 'active'])->assertForbidden();
    identityApi('PATCH', '/api/v1/memberships/'.$target->id, $token, ['role_id' => tenantRole($org, RoleName::Viewer)->id, 'status' => 'suspended'])->assertOk()->assertJsonPath('data.status', 'suspended');
    expect($target->refresh()->status->value)->toBe('suspended');
});

it('only permits revoking the actors own tokens and never authenticates API requests with web cookies alone', function () {
    [$owner,$org] = tenantWorkspace();
    tenantActor($owner, $org);
    $token = app(IssueApiToken::class)($owner, 'password', 'Mine', ['memberships.view'], 30);
    $record = PersonalAccessToken::firstOrFail();
    $other = User::factory()->create();
    $this->actingAs($other);
    expect(fn () => app(RevokeApiToken::class)($other, 'password', $record->id))->toThrow(ModelNotFoundException::class);
    tenantActor($owner,$org);
    $this->getJson('/api/v1/memberships')->assertUnauthorized();
    app(RevokeApiToken::class)($owner,'password',$record->id);
    identityApi('GET','/api/v1/memberships',$token)->assertUnauthorized();
});
