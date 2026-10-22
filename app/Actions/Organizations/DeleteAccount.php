<?php

namespace App\Actions\Organizations;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteAccount
{
    public function __invoke(User $user): void
    {
        DB::transaction(function () use ($user) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->memberships()->exists()) {
                throw ValidationException::withMessages(['password' => 'Remove your organization memberships before deleting your account. Transfer ownership first if necessary.']);
            }

            $locked->delete();
        });
    }
}
