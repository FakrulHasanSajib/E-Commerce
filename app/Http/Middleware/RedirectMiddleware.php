<?php

namespace App\Http\Middleware;

use App\Services\Seo\RedirectService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectMiddleware
{
    public function __construct(protected RedirectService $redirectService) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $redirect = $this->redirectService->resolveRedirect($request->getPathInfo());

        if ($redirect) {
            return redirect($redirect['target_url'], $redirect['status_code']);
        }

        $response = $next($request);

        if ($response->getStatusCode() === 404) {
            $this->redirectService->log404NotFound($request->fullUrl(), $request->ip(), $request->userAgent());
        }

        return $response;
    }
}
