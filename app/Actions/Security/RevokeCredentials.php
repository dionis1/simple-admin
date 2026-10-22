<?php

namespace App\Actions\Security;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RevokeCredentials
{
    public function __invoke(User $user, bool $includingCurrent = false): void
    {
        $sessions = DB::table('sessions')->where('user_id', $user->id);
        if (! $includingCurrent && request()->hasSession()) {
            $sessions->where('id', '!=', session()->getId());
        }
        $sessions->delete();
        PersonalAccessToken::where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $user->id)->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }
}
