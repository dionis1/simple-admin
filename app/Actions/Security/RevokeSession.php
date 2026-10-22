<?php

namespace App\Actions\Security;

use App\Models\User;
use App\Notifications\SecurityNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RevokeSession
{
    public function __invoke(User $actor, string $password, string $id): void
    {
        $user = app(ConfirmPassword::class)($actor, $password);
        DB::transaction(function () use ($user, $id) {
            abort_unless(DB::table('sessions')->where('user_id', $user->id)->where('id', $id)->delete() > 0, 404);
            $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
            $user->notify(new SecurityNotice('A browser session was revoked.'));
        });
    }
}
