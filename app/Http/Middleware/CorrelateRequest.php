<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelateRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        // Never trust an inbound correlation ID; it can contain forged log fields.
        $id = (string) Str::uuid();
        $request->attributes->set('correlation_id', $id);
        Log::withContext(['correlation_id' => $id]);
        try {
            $response = $next($request);
            $response->headers->set('X-Correlation-ID', $id);

            return $response;
        } finally {
            Log::withoutContext();
        }
    }
}
