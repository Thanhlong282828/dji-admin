<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class RequestCorrelationContext
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $candidate = $request->headers->get('X-Request-ID');
        $requestId = is_string($candidate) && preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $candidate) === 1
            ? $candidate
            : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        Log::withContext([
            'request_id' => $requestId,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
