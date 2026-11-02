<?php

namespace App\Http\Middleware;

use App\Support\HttpMetricsRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RequestCorrelationContext
{
    public function __construct(private HttpMetricsRecorder $metrics) {}

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
            'correlation_id' => $requestId,
            'component' => 'http',
            'environment' => app()->environment(),
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
        ]);

        $startedAt = hrtime(true);
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->metrics->recordSafely(500, (hrtime(true) - $startedAt) / 1_000_000);

            throw $exception;
        }
        $this->metrics->recordSafely($response->getStatusCode(), (hrtime(true) - $startedAt) / 1_000_000);
        $response->headers->set('X-Request-ID', $requestId);
        $revision = config('release.revision');
        if (is_string($revision) && preg_match('/^[A-Za-z0-9._-]{1,128}$/', $revision) === 1) {
            $response->headers->set('X-Release-Revision', $revision);
        }

        return $response;
    }
}
