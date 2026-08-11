<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminTwoFactorMiddleware
{
    /**
     * Handle an incoming request for 2FA challenge on admin routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->two_factor_confirmed_at) {
            // Check if 2FA session challenge passed or X-2FA-Code header provided
            if (! $request->session()->get('2fa_verified', false) && ! $request->header('X-2FA-Code')) {
                return response()->json([
                    'message' => 'Two-factor authentication code required.',
                    'two_factor_required' => true,
                ], 403);
            }
        }

        return $next($request);
    }
}
