<?php

namespace App\Actions\Security;

use App\Enums\AccessStatus;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\SecurityNotice;
use App\Tenancy\OwnerProtection;
use Illuminate\Support\Facades\DB;

class AccountLifecycle
{
    public function __invoke(User $actor, string $password, bool $reactivate): void
    {
        app(ConfirmPassword::class)($actor, $password);
        DB::transaction(function () use ($actor, $reactivate) {
            $user = User::query()->lockForUpdate()->findOrFail($actor->id);
            $ids = $user->memberships()->orderBy('organization_id')->pluck('organization_id');
            Organization::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            abort_if($user->status === AccessStatus::Suspended, 403);
            if (! $reactivate) {
                foreach ($user->memberships()->get() as $membership) {
                    app(OwnerProtection::class)->ensureMayChange($membership, null, AccessStatus::Deactivated);
                }
            }
            $user->forceFill(['status' => $reactivate ? AccessStatus::Active : AccessStatus::Deactivated])->save();
            if ($reactivate) {
                $user->notify(new SecurityNotice('Your account was reactivated.'));
            }
        }, 3);
    }
}
