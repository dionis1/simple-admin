<?php

namespace App\Http\Middleware;

use App\Enums\AccessStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SerializeMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $challenge = $request->isMethod('POST') && $request->is('two-factor-challenge');
        $management = ! $request->isMethod('GET') && $request->is('user/two-factor-*', 'user/confirmed-two-factor-authentication');
        if (! $challenge && ! $management) {
            return $next($request);
        }
        $id = $challenge ? $request->session()->get('login.id') : $request->user()?->getAuthIdentifier();
        abort_unless(is_int($id) && $id > 0, 403);

        return DB::transaction(function () use ($request, $next, $challenge, $id) {
            $user = User::query()->lockForUpdate()->findOrFail($id);
            abort_if($user->status === AccessStatus::Suspended || (! $challenge && ! $user->isActive()), 403);
            if (! $challenge) {
                Auth::guard('web')->setUser($user);
            }

            return $next($request);
        });
    }
}
