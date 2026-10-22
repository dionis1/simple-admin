<?php

namespace App\Http\Middleware;

use App\Actions\Governance\SupportAccess;
use App\Governance\Audit;
use App\Models\SupportGrant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SupportSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has(SupportAccess::SESSION_KEY)) {
            return $next($request);
        }
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $service = app(SupportAccess::class);
        if ($request->isMethod('POST') && $request->is('support/stop')) {
            return $next($request);
        }
        $grantId = $request->session()->get(SupportAccess::SESSION_KEY);
        abort_unless(is_int($grantId), 403);
        $grant = SupportGrant::where('actor_id', $actor->id)->find($grantId);
        try {
            abort_unless($grant !== null && $grant->started_at !== null && $grant->session_hash !== null
                && hash_equals($grant->session_hash, $service->sessionHash())
                && $request->session()->get(TenantContext::SESSION_KEY) === $grant->organization_id, 403);
            $target = $service->validateGrant($actor, $grant);
        } catch (HttpException $exception) {
            $service->stop($actor);
            abort(403, 'Support access ended.');
        } catch (ModelNotFoundException $exception) {
            $service->stop($actor);
            abort(403, 'Support access ended.');
        }
        $request->attributes->set('support_subject_id', $target->user_id);
        $request->attributes->set('support_actor_id', $actor->id);
        // No personal settings, credentials, export, API, Livewire POST, switching or writes.
        if (! $request->isMethod('GET') || ! $request->is('dashboard', 'organization', 'structure')) {
            app(Audit::class)->record('support.blocked', $grant->organization_id, 'SupportGrant', $grant->id, [], $actor);
            abort(403, 'Support access is read-only. End it before continuing.');
        }
        app(Audit::class)->record('support.viewed', $grant->organization_id, 'SupportGrant', $grant->id, [], $actor);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
