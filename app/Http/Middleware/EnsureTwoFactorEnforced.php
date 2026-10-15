<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorEnforced
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $isProduction = app()->environment('production');
        $mustVerify = $user->hasTwoFactorEnabled() || ($isProduction && $user->isAdmin());

        if ($mustVerify && !$request->session()->get('auth.mfa_verified', false)) {
            // Allow routes required to set up or verify 2FA
            if ($request->is('api/auth/mfa*') || $request->is('api/auth/logout') || $request->is('logout')) {
                return $next($request);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Verifikasi autentikasi dua faktor (MFA/2FA) diperlukan sebelum melanjutkan.',
                    'mfa_required' => true,
                    'has_2fa_setup' => $user->hasTwoFactorEnabled(),
                ], 403);
            }

            return redirect()->route('login');
        }

        return $next($request);
    }
}
