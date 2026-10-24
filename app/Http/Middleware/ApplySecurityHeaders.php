<?php

namespace App\Http\Middleware;

use App\Support\Security\SecurityHeaderPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ApplySecurityHeaders
{
    public function __construct(private SecurityHeaderPolicy $policy) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach ($this->policy->required() as $name => $value) {
            $response->headers->set($name, $value);
        }

        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
