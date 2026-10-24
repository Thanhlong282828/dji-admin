<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    private const SUPPORTED = ['default', 'vi', 'en', 'zh_CN'];

    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('app.locale');
        $fallback = is_string($configured) ? $configured : 'en';
        $presentationLocale = $request->hasSession()
            ? $request->session()->get('locale', 'default')
            : 'default';
        if (! is_string($presentationLocale) || ! in_array($presentationLocale, self::SUPPORTED, true)) {
            $presentationLocale = 'default';
        }

        $request->attributes->set('presentation_locale', $presentationLocale);
        app()->setLocale($presentationLocale === 'default' ? $fallback : $presentationLocale);
        $response = $next($request);
        if (! $response instanceof Response) {
            throw new \RuntimeException('Locale middleware pipeline must return a response.');
        }

        return $response;
    }
}
