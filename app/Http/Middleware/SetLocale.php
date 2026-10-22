<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user() instanceof User ? $request->user()->locale : $request->session()->get('locale', 'en');
        app()->setLocale(in_array($locale, ['en', 'de'], true) ? $locale : 'en');

        $response = $next($request);
        if ($request->is('api/*', 'settings/*', 'user/two-factor-*', 'two-factor-challenge', 'invitations/accept/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Referrer-Policy', 'no-referrer');
        }

        return $response;
    }
}
