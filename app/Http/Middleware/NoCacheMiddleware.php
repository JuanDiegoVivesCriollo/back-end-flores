<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NoCacheMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Agregar headers anti-cache solo para rutas API específicas
        if ($request->is('api/v1/catalog/*') ||
            $request->is('api/v1/admin/flowers*') ||
            $request->is('api/v1/flowers-temp*')) {

            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');

            // Headers adicionales para debug
            $response->headers->set('X-Debug-Timestamp', time());
            $response->headers->set('X-Debug-No-Cache', 'applied');
        }

        return $response;
    }
}
