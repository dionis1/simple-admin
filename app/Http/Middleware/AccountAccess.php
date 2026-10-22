<?php

namespace App\Http\Middleware;

use App\Enums\AccessStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AccountAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && User::whereKey($user->id)->where('status', AccessStatus::Suspended)->exists()) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
            }
            abort(403, __('Your account is suspended.'));
        }

        return $next($request);
    }
}
