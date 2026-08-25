<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccountAccessEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->access_enabled, 403, 'Tài khoản đã bị vô hiệu hóa.');

        $response = $next($request);
        abort_unless($response instanceof Response, 500);

        return $response;
    }
}
