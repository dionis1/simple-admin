<?php

namespace App\Actions\Security;

use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Notifications\SecurityNotice;

class RevokeApiToken
{
    public function __invoke(User $actor, string $password, int $id): void
    {
        app(ConfirmPassword::class)($actor, $password);
        PersonalAccessToken::where('tokenable_type', $actor->getMorphClass())->where('tokenable_id', $actor->id)->findOrFail($id)->delete();
        $actor->notify(new SecurityNotice('An API token was revoked.'));
    }
}
