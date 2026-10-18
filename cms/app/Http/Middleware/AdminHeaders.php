<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if ($request->is('admin', 'admin/*', 'livewire/*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('Referrer-Policy', 'same-origin');
            $response->headers->set('X-Content-Type-Options', 'nosniff');
        }

        return $response;
    }
}
