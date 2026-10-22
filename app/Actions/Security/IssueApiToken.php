<?php

namespace App\Actions\Security;

use App\Enums\PermissionKey;
use App\Models\Organization;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Notifications\SecurityNotice;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class IssueApiToken
{
    /** @param array<int, string> $abilities */
    public function __invoke(User $actor, string $password, string $name, array $abilities, int $days): string
    {
        app(ConfirmPassword::class)($actor, $password);
        Validator::make(compact('name', 'abilities', 'days'), [
            'name' => ['required', 'string', 'max:80'], 'days' => ['required', 'integer', 'between:1,90'],
            'abilities' => ['required', 'array', 'min:1', 'max:20'], 'abilities.*' => ['required', 'string', 'distinct', Rule::in(array_column(PermissionKey::cases(), 'value'))],
        ])->validate();

        return DB::transaction(function () use ($actor, $name, $abilities, $days) {
            $context = app(TenantContext::class);
            $org = $context->resolve($actor)->organization_id;
            Organization::query()->lockForUpdate()->findOrFail($org);
            foreach ($abilities as $ability) {
                abort_unless($context->allows($actor, $ability), 403);
            }
            $plain = Str::random(64);
            $token = new PersonalAccessToken;
            $token->forceFill(['tokenable_type' => $actor->getMorphClass(), 'tokenable_id' => $actor->id, 'organization_id' => $org, 'name' => $name, 'token' => hash('sha256', $plain), 'abilities' => $abilities, 'expires_at' => now()->addDays($days)])->save();
            $actor->notify(new SecurityNotice('An API token was created.'));

            return $token->id.'|'.$plain;
        });
    }
}
