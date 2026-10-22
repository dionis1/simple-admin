<?php

namespace App\Http\Responses;

use App\Enums\AccessStatus;
use App\Models\User;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;

class LoginResponse implements LoginResponseContract, TwoFactorLoginResponse
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->noContent();
        }
        $user = $request->user();
        if ($user instanceof User && $user->status === AccessStatus::Deactivated) {
            return redirect()->route('identity.security');
        }

        return redirect()->intended(config('fortify.home'));
    }
}
