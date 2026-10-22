<?php

namespace App\Actions\Security;

use App\Enums\AccessStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ConfirmPassword
{
    public function __invoke(User $actor, string $password): User
    {
        $key = 'security-password:'.$actor->id.'|'.request()->ip();
        $user = User::findOrFail($actor->id);
        abort_if($user->status === AccessStatus::Suspended, 403);
        if (RateLimiter::tooManyAttempts($key, 5) || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['currentPassword' => __('The password is incorrect or too many attempts were made. Try again shortly.')]);
        }
        RateLimiter::clear($key);
        session()->put('auth.password_confirmed_at', time());

        return $user;
    }
}
