<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Akun Anda dinonaktifkan atau ditangguhkan oleh administrator.',
                ], 403);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Akun Anda dinonaktifkan atau ditangguhkan oleh administrator.',
            ]);
        }

        return $next($request);
    }
}
