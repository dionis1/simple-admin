<?php

namespace App\Actions\Security;

use App\Governance\Audit;
use App\Models\PersonalAccessToken;
use App\Models\SupportGrant;
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
        SupportGrant::whereNull('ended_at')->where(function ($query) use ($user) {
            $query->where('actor_id', $user->id)->orWhereIn('target_membership_id', $user->memberships()->select('id'));
        })->get()->each(function (SupportGrant $grant): void {
            $grant->forceFill(['ended_at' => now()])->save();
        });
        app(Audit::class)->record('security.credentials_revoked', null, 'User', $user->id);
        PersonalAccessToken::where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $user->id)->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }
}
